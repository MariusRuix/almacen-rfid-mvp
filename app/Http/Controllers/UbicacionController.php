<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Movimiento;
use App\Models\Ubicacion;
use App\Services\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Alta, edición y reubicación de ubicaciones. La vista es el mapa (InventarioController::mapa). */
class UbicacionController extends Controller
{
    /** Crea un pasillo completo: módulos × niveles con código PASILLO-MÓDULO-NIVEL. */
    public function generarPasillo(Request $request)
    {
        $request->merge(['pasillo' => strtoupper(trim((string) $request->input('pasillo')))]);
        $datos = $request->validate([
            'pasillo' => ['required', 'regex:/^[A-Z]{1,2}$/'],
            'modulos' => ['required', 'integer', 'min:1', 'max:40'],
            'niveles' => ['required', 'integer', 'min:1', 'max:9'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'zona' => ['required', Rule::in(['rack', 'estanteria'])],
            'area_id' => ['required', 'exists:areas,id'],
        ], [
            'pasillo.regex' => 'El pasillo es una o dos letras, por ejemplo C.',
            'modulos.*' => 'Entre 1 y 40 módulos.',
            'niveles.*' => 'Entre 1 y 9 niveles.',
            'capacidad.*' => 'Indica la capacidad en piezas por posición.',
        ]);

        $codigos = [];
        for ($m = 1; $m <= $datos['modulos']; $m++) {
            for ($n = 1; $n <= $datos['niveles']; $n++) {
                $codigos[] = sprintf('%s-%02d-%d', $datos['pasillo'], $m, $n);
            }
        }
        $existentes = Ubicacion::whereIn('codigo', $codigos)->pluck('codigo');
        if ($existentes->isNotEmpty()) {
            return back()->withErrors(['pasillo' => 'Ya existen '.$existentes->count().' de esas ubicaciones (por ejemplo '.$existentes->first().'). Usa otra letra o menos módulos.']);
        }

        DB::transaction(function () use ($codigos, $datos) {
            foreach ($codigos as $codigo) {
                Ubicacion::create(['codigo' => $codigo, 'zona' => $datos['zona'], 'capacidad' => $datos['capacidad'], 'area_id' => $datos['area_id']]);
            }
        });

        return back()->with('ok', 'Pasillo '.$datos['pasillo'].' creado: '.count($codigos).' posiciones.');
    }

    /** Una ubicación suelta: posición de piso, adicional de temporada, cuarentena… */
    public function store(Request $request)
    {
        $request->merge(['codigo' => strtoupper(trim((string) $request->input('codigo')))]);
        $datos = $request->validate([
            'codigo' => ['required', 'max:20', 'regex:/^[A-Z0-9-]+$/', 'unique:ubicaciones,codigo'],
            'zona' => ['required', Rule::in([...Ubicacion::ALMACENAJE, ...Ubicacion::OPERATIVAS])],
            'capacidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'area_id' => ['required', 'exists:areas,id'],
        ], [
            'codigo.unique' => 'Ya existe una ubicación con ese código.',
            'codigo.regex' => 'Solo letras, números y guiones, por ejemplo AD-03.',
            'codigo.required' => 'Captura el código.',
            'capacidad.*' => 'Indica la capacidad en piezas.',
        ]);
        $u = Ubicacion::create($datos);

        return back()->with('ok', 'Ubicación '.$u->codigo.' creada.');
    }

    public function update(Request $request, Ubicacion $ubicacion)
    {
        $operativa = in_array($ubicacion->zona, Ubicacion::OPERATIVAS, true);
        $datos = $request->validate([
            'capacidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'zona' => ['required', Rule::in($operativa ? [$ubicacion->zona] : Ubicacion::ALMACENAJE)],
            'activa' => ['required', 'boolean'],
            'area_id' => ['required', 'exists:areas,id'],
        ], ['zona.in' => 'Las zonas de recepción, preparación e incidencias no cambian de tipo.']);

        if (! $datos['activa'] && $operativa) {
            return back()->with('error', 'Las zonas del flujo (recepción, preparación, incidencias) no se desactivan.');
        }
        $piezas = (int) Existencia::where('ubicacion_id', $ubicacion->id)->sum('cantidad');
        if (! $datos['activa'] && $piezas > 0) {
            return back()->with('error', $ubicacion->codigo.' tiene '.$piezas.' piezas: reubícalas antes de desactivarla.');
        }

        $ubicacion->update($datos);

        return back()->with('ok', $ubicacion->codigo.' actualizada'.($datos['activa'] ? '.' : ': ya no se sugiere ni recibe mercancía.'));
    }

    public function destroy(Ubicacion $ubicacion)
    {
        $usada = Movimiento::where('ubicacion_origen_id', $ubicacion->id)->orWhere('ubicacion_destino_id', $ubicacion->id)->exists()
            || Existencia::where('ubicacion_id', $ubicacion->id)->exists();
        if ($usada || in_array($ubicacion->zona, Ubicacion::OPERATIVAS, true)) {
            return back()->with('error', 'No se puede borrar '.$ubicacion->codigo.': ya tiene historial. Desactívala en su lugar.');
        }
        $ubicacion->delete();

        return back()->with('ok', 'Ubicación '.$ubicacion->codigo.' borrada.');
    }

    /** Mueve existencia almacenada de una ubicación a otra (mismo SKU, pedimento y estado). */
    public function reubicar(Request $request, Inventario $inventario)
    {
        $datos = $request->validate([
            'existencia_id' => ['required', 'exists:existencias,id'],
            'destino_id' => ['required', 'exists:ubicaciones,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ], ['cantidad.*' => 'Indica cuántas piezas mover (1 o más).']);

        $existencia = Existencia::with(['producto', 'ubicacion'])->findOrFail($datos['existencia_id']);
        $destino = Ubicacion::with('area')->findOrFail($datos['destino_id']);

        if ($existencia->estado !== 'disponible' || ! in_array($existencia->ubicacion->zona, Ubicacion::ALMACENAJE, true)) {
            return back()->with('error', 'Solo se reubica mercancía almacenada en racks, estantes, piso o adicionales.');
        }
        if (! $destino->disponible() || ! in_array($destino->zona, Ubicacion::ALMACENAJE, true) || $destino->id === $existencia->ubicacion_id) {
            return back()->with('error', 'Elige una ubicación de almacenaje activa y distinta a la actual.');
        }
        if ($datos['cantidad'] > $existencia->cantidad) {
            return back()->with('error', 'Solo hay '.$existencia->cantidad.' piezas en '.$existencia->ubicacion->codigo.'.');
        }

        $inventario->mover('reubicacion', $existencia->producto_id, $existencia->pedimento_id,
            ['ubicacion_id' => $existencia->ubicacion_id, 'estado' => 'disponible'],
            ['ubicacion_id' => $destino->id, 'estado' => 'disponible'],
            $datos['cantidad'], null, null, 'Reubicación '.$existencia->ubicacion->codigo.' → '.$destino->codigo);

        $ocupadas = (int) Existencia::where('ubicacion_id', $destino->id)->sum('cantidad');
        $aviso = $ocupadas > $destino->capacidad ? ' Atención: '.$destino->codigo.' queda por encima de su capacidad.' : '';

        return back()->with('ok', $datos['cantidad'].' piezas de '.$existencia->producto->sku.' movidas a '.$destino->codigo.'.'.$aviso);
    }
}

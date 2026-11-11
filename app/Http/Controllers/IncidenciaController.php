<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Incidencia;
use App\Models\Ubicacion;
use App\Services\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IncidenciaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Incidencias/Index', [
            'incidencias' => Incidencia::with(['producto', 'pedimento', 'destino', 'reporto', 'resolvio'])
                ->orderByRaw("case when estado = 'abierta' then 0 else 1 end")->latest('id')->get()
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'folio' => $i->folio,
                    'tipo' => $i->tipo,
                    'estado' => $i->estado,
                    'cantidad' => $i->cantidad,
                    'descripcion' => $i->descripcion,
                    'resolucion' => $i->resolucion,
                    'origen_folio' => $i->origen_folio,
                    'origen_type' => $i->origen_type,
                    'origen_id' => $i->origen_id,
                    'producto' => Presentar::producto($i->producto),
                    'pedimento' => $i->pedimento?->numero,
                    'destino' => $i->destino?->codigo,
                    'reporto' => $i->reporto?->name,
                    'resolvio' => $i->resolvio?->name,
                    'fecha' => $i->created_at->toDateTimeString(),
                    'puede_liberar' => $i->tipo === 'accesorio_faltante' && $i->estado === 'abierta',
                ]),
            'ubicaciones' => Ubicacion::whereIn('zona', ['rack', 'adicional'])->orderBy('codigo')->get(['id', 'codigo']),
        ]);
    }

    public function resolver(Request $request, Incidencia $incidencia, Inventario $inventario)
    {
        abort_if($incidencia->estado !== 'abierta', 422);
        $datos = $request->validate([
            'resolucion' => ['required', 'string', 'max:250'],
            'liberar_a' => ['nullable', 'exists:ubicaciones,id'],
        ], [], ['resolucion' => 'resolución']);

        DB::transaction(function () use ($incidencia, $datos, $inventario, $request) {
            // Accesorio completado: las piezas vuelven a estar disponibles.
            if ($incidencia->tipo === 'accesorio_faltante' && ! empty($datos['liberar_a'])) {
                $zonaInc = Ubicacion::zona('incidencia');
                $hay = (int) Existencia::where(['producto_id' => $incidencia->producto_id, 'pedimento_id' => $incidencia->pedimento_id,
                    'ubicacion_id' => $zonaInc->id, 'estado' => 'incidencia'])->value('cantidad');
                $cantidad = min($hay, $incidencia->cantidad);
                if ($cantidad > 0) {
                    $inventario->mover('liberacion', $incidencia->producto_id, $incidencia->pedimento_id,
                        ['ubicacion_id' => $zonaInc->id, 'estado' => 'incidencia'],
                        ['ubicacion_id' => (int) $datos['liberar_a'], 'estado' => 'disponible'],
                        $cantidad, $incidencia, $incidencia->folio, $datos['resolucion']);
                }
            }
            $incidencia->update(['estado' => 'resuelta', 'resolucion' => $datos['resolucion'],
                'resolvio_id' => $request->user()->id, 'resuelta_at' => now()]);
        });

        return back()->with('ok', $incidencia->folio.' resuelta.');
    }
}

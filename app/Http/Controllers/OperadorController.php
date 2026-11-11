<?php

namespace App\Http\Controllers;

use App\Models\Incidencia;
use App\Models\Pedido;
use App\Models\PedidoDestino;
use App\Models\Recepcion;
use App\Models\TareaSurtido;
use App\Models\Ubicacion;
use App\Services\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Vista de piso: una instrucción a la vez, botones grandes. */
class OperadorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Operador/Index', [
            'recepciones' => Recepcion::with('pedimento')->withSum('lineas', 'piezas_esperadas')
                ->withCount(['lineas as por_ubicar' => fn ($q) => $q->whereNull('ubicada_at')])
                ->get()->filter(fn ($r) => $r->estado !== 'cerrada' || $r->por_ubicar > 0)->values()
                ->map(fn ($r) => [
                    'id' => $r->id, 'folio' => $r->folio, 'estado' => $r->estado, 'por_ubicar' => $r->por_ubicar,
                    'pedimento' => Presentar::pedimento($r->pedimento), 'piezas' => (int) $r->lineas_sum_piezas_esperadas,
                ]),
            'pedidos' => Pedido::withCount(['tareas as pendientes' => fn ($q) => $q->where('estado', 'pendiente')])
                ->withCount('tareas')->withCount('destinos')
                ->whereIn('estado', ['en_surtido', 'en_separacion', 'en_revision', 'listo'])->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'folio' => $p->folio, 'estado' => $p->estado,
                    'tareas' => $p->tareas_count, 'pendientes' => $p->pendientes, 'destinos' => $p->destinos_count,
                ]),
        ]);
    }

    public function surtido(Pedido $pedido): Response
    {
        $pedido->load(['tareas.producto', 'tareas.pedimento', 'tareas.ubicacion']);
        $actual = $pedido->tareas->firstWhere('estado', 'pendiente');

        return Inertia::render('Operador/Surtido', [
            'pedido' => ['id' => $pedido->id, 'folio' => $pedido->folio, 'estado' => $pedido->estado],
            'tarea' => $actual ? [
                'id' => $actual->id,
                'orden' => $actual->orden,
                'producto' => Presentar::producto($actual->producto),
                'pedimento' => Presentar::pedimento($actual->pedimento),
                'ubicacion' => Presentar::ubicacion($actual->ubicacion),
                'cantidad' => $actual->cantidad,
            ] : null,
            'total' => $pedido->tareas->count(),
            'hechas' => $pedido->tareas->where('estado', '!=', 'pendiente')->count(),
            'siguiente' => $actual ? Presentar::ubicacion($pedido->tareas->where('estado', 'pendiente')->skip(1)->first()?->ubicacion) : null,
            'zonaPreparacion' => Ubicacion::zona('preparacion')->codigo,
        ]);
    }

    public function confirmarTarea(Request $request, TareaSurtido $tarea, Inventario $inventario)
    {
        abort_if($tarea->estado !== 'pendiente', 422);
        $datos = $request->validate(['tomada' => ['required', 'integer', 'min:0', 'max:'.$tarea->cantidad]],
            ['tomada.max' => 'No se pueden tomar más piezas de las indicadas.']);
        $tomada = (int) $datos['tomada'];
        $pedido = $tarea->pedido;

        DB::transaction(function () use ($tarea, $tomada, $inventario, $pedido, $request) {
            if ($tomada > 0) {
                $inventario->mover('surtido', $tarea->producto_id, $tarea->pedimento_id,
                    ['ubicacion_id' => $tarea->ubicacion_id, 'estado' => 'disponible'],
                    ['ubicacion_id' => Ubicacion::zona('preparacion')->id, 'estado' => 'en_preparacion'],
                    $tomada, $pedido, $pedido->folio);
            }
            $faltan = $tarea->cantidad - $tomada;
            $tarea->update(['cantidad_tomada' => $tomada, 'estado' => $faltan > 0 ? 'con_faltante' : 'tomada',
                'user_id' => $request->user()->id, 'tomada_at' => now()]);

            if ($faltan > 0) {
                Incidencia::create([
                    'folio' => Incidencia::siguienteFolio(), 'tipo' => 'faltante',
                    'origen_type' => 'pedido', 'origen_id' => $pedido->id, 'origen_folio' => $pedido->folio,
                    'producto_id' => $tarea->producto_id, 'pedimento_id' => $tarea->pedimento_id, 'cantidad' => $faltan,
                    'descripcion' => "En {$tarea->ubicacion->codigo} faltaron {$faltan} piezas al surtir.",
                    'reporto_id' => $request->user()->id,
                ]);
            }

            if (! $pedido->tareas()->where('estado', 'pendiente')->exists()) {
                $pedido->update(['estado' => 'en_separacion']);
            }
        });

        return back()->with('ok', $tomada.' piezas tomadas.');
    }

    public function separacion(Pedido $pedido): Response
    {
        $pedido->load(['destinos.destino', 'destinos.lineas.producto', 'tareas']);
        $tomado = $pedido->tareas->groupBy('producto_id')->map(fn ($t) => (int) $t->sum('cantidad_tomada'));

        return Inertia::render('Operador/Separacion', [
            'pedido' => PedidoController::detalle($pedido),
            'tomadoPorProducto' => $tomado,
        ]);
    }

    public function confirmarDestino(Request $request, PedidoDestino $destino)
    {
        $pedido = $destino->pedido;
        abort_unless(in_array($pedido->estado, ['en_separacion', 'en_revision', 'listo']), 422);
        $destino->load(['lineas', 'destino']);

        $datos = $request->validate([
            'lineas' => ['required', 'array'],
            'lineas.*' => ['required', 'integer', 'min:0'],
            'actividades' => ['array'],
            'actividades.*' => ['boolean'],
            'bultos' => ['required', 'integer', 'min:1', 'max:99'],
        ], ['bultos.min' => 'Indica cuántos bultos lleva el destino.']);

        DB::transaction(function () use ($destino, $datos, $pedido) {
            foreach ($destino->lineas as $l) {
                $l->update(['cantidad_separada' => (int) ($datos['lineas'][$l->id] ?? $l->cantidad), 'cantidad_leida' => null]);
            }
            $actividades = array_intersect_key($datos['actividades'] ?? [], $destino->actividades());
            $destino->update($actividades + ['bultos' => $datos['bultos'], 'estado' => 'separado', 'verificado_at' => null]);

            // Un destino re-separado se vuelve a leer, así que el pedido regresa a revisión.
            if (! $pedido->destinos()->where('estado', 'pendiente')->exists()) {
                $pedido->update(['estado' => 'en_revision']);
            }
        });

        return back()->with('ok', 'Destino '.$destino->destino->codigo.' separado.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Incidencia;
use App\Models\Pedido;
use App\Models\PedidoDestino;
use App\Models\Salida;
use App\Models\TareaSurtido;
use App\Models\Ubicacion;
use App\Services\Inventario;
use App\Services\Rfid\Epc;
use App\Services\Rfid\LectorRfid;
use App\Services\Rfid\LectorSimulado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PedidoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pedidos/Index', [
            'pedidos' => Pedido::withCount('destinos')->withSum('lineas', 'cantidad')->latest('id')->get()->map(fn ($p) => [
                'id' => $p->id,
                'folio' => $p->folio,
                'propietario' => $p->propietario,
                'estado' => $p->estado,
                'fecha_compromiso' => $p->fecha_compromiso->toDateString(),
                'destinos' => $p->destinos_count,
                'piezas' => (int) $p->lineas_sum_cantidad,
            ]),
        ]);
    }

    public function show(Pedido $pedido): Response
    {
        return Inertia::render('Pedidos/Show', [
            'pedido' => self::detalle($pedido),
            'regla' => config('demo.regla_surtido'),
        ]);
    }

    public static function detalle(Pedido $pedido): array
    {
        $pedido->load(['destinos.destino', 'destinos.lineas.producto', 'tareas.producto', 'tareas.pedimento', 'tareas.ubicacion', 'salida.autorizo']);

        return [
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'propietario' => $pedido->propietario,
            'estado' => $pedido->estado,
            'fecha_compromiso' => $pedido->fecha_compromiso->toDateString(),
            'destinos' => $pedido->destinos->map(fn (PedidoDestino $d) => [
                'id' => $d->id,
                'codigo' => $d->destino->codigo,
                'nombre' => $d->destino->nombre,
                'tipo' => $d->destino->tipo,
                'estado' => $d->estado,
                'bultos' => $d->bultos,
                'verificado_at' => $d->verificado_at?->toDateTimeString(),
                'actividades' => collect($d->actividades())->map(fn ($etiqueta, $campo) => [
                    'campo' => $campo, 'etiqueta' => $etiqueta, 'hecha' => (bool) $d->{$campo},
                ])->values(),
                'lineas' => $d->lineas->map(fn ($l) => [
                    'id' => $l->id,
                    'producto' => Presentar::producto($l->producto),
                    'cantidad' => $l->cantidad,
                    'separada' => $l->cantidad_separada,
                    'leida' => $l->cantidad_leida,
                ]),
            ]),
            'tareas' => $pedido->tareas->map(fn (TareaSurtido $t) => [
                'id' => $t->id,
                'orden' => $t->orden,
                'producto' => Presentar::producto($t->producto),
                'pedimento' => Presentar::pedimento($t->pedimento),
                'ubicacion' => Presentar::ubicacion($t->ubicacion),
                'cantidad' => $t->cantidad,
                'tomada' => $t->cantidad_tomada,
                'estado' => $t->estado,
            ]),
            'salida' => $pedido->salida ? [
                'folio' => $pedido->salida->folio,
                'transportista' => $pedido->salida->transportista,
                'placas' => $pedido->salida->placas,
                'sello' => $pedido->salida->sello,
                'piezas' => $pedido->salida->piezas,
                'autorizo' => $pedido->salida->autorizo->name,
                'fecha' => $pedido->salida->created_at->toDateTimeString(),
            ] : null,
        ];
    }

    /** Convierte el pedido en instrucciones de toma, aplicando la regla de surtido por pedimento. */
    public function liberar(Pedido $pedido)
    {
        abort_if($pedido->estado !== 'nuevo', 422);
        $pedido->load('lineas.producto');
        $necesidad = $pedido->lineas->groupBy('producto_id')->map->sum('cantidad');
        $zonasSurtibles = Ubicacion::whereIn('zona', ['rack', 'adicional'])->pluck('id');

        $tareas = [];
        foreach ($necesidad as $productoId => $faltan) {
            $fuentes = Existencia::with(['pedimento', 'ubicacion'])
                ->where('producto_id', $productoId)->where('estado', 'disponible')
                ->whereIn('ubicacion_id', $zonasSurtibles)->where('cantidad', '>', 0)->get()
                ->sortBy([fn ($a, $b) => $a->pedimento->fecha_pago <=> $b->pedimento->fecha_pago,
                    fn ($a, $b) => $a->cantidad <=> $b->cantidad]);

            foreach ($fuentes as $e) {
                if ($faltan <= 0) {
                    break;
                }
                $toma = min($faltan, $e->cantidad);
                $tareas[] = ['producto_id' => $productoId, 'pedimento_id' => $e->pedimento_id, 'ubicacion_id' => $e->ubicacion_id,
                    'cantidad' => $toma, 'codigo' => $e->ubicacion->codigo];
                $faltan -= $toma;
            }

            if ($faltan > 0) {
                $sku = $pedido->lineas->firstWhere('producto_id', $productoId)->producto->sku;

                return back()->with('error', "No hay existencia disponible suficiente de {$sku}: faltan {$faltan} piezas.");
            }
        }

        // Ruta de toma ordenada por ubicación.
        usort($tareas, fn ($a, $b) => strcmp($a['codigo'], $b['codigo']));

        DB::transaction(function () use ($pedido, $tareas) {
            foreach ($tareas as $i => $t) {
                unset($t['codigo']);
                $pedido->tareas()->create($t + ['orden' => $i + 1]);
            }
            $pedido->update(['estado' => 'en_surtido']);
        });

        return back()->with('ok', count($tareas).' instrucciones de toma enviadas al operador.');
    }

    /** Lectura RFID del bulto de un destino durante la revisión. */
    public function leerDestino(PedidoDestino $destino, LectorRfid $lector)
    {
        $pedido = $destino->pedido;
        abort_unless(in_array($pedido->estado, ['en_revision', 'listo']), 422);
        $destino->load(['lineas', 'destino']);

        $esperado = $destino->lineas->groupBy('producto_id')->map->sum('cantidad_separada')->all();
        $conteo = Epc::contarPorProducto($lector->leer($esperado, $pedido->folio.':'.$destino->destino->codigo));

        $cuadra = true;
        foreach ($destino->lineas as $l) {
            $leida = $conteo[$l->producto_id] ?? 0;
            $l->update(['cantidad_leida' => $leida]);
            $cuadra = $cuadra && $leida === $l->cantidad_separada;
        }

        $destino->update($cuadra
            ? ['estado' => 'verificado', 'verificado_at' => now()]
            : ['estado' => 'con_diferencia', 'verificado_at' => null]);
        $this->actualizarEstado($pedido);

        return back()->with($cuadra ? 'ok' : 'error', $cuadra
            ? $destino->destino->codigo.' cuadra con lo preparado.'
            : $destino->destino->codigo.' no cuadra: revisa las líneas marcadas.');
    }

    /** El operador corrigió físicamente el bulto; la siguiente lectura confirma. */
    public function corregirDestino(Request $request, PedidoDestino $destino, Inventario $inventario)
    {
        $destino->load(['pedido', 'destino', 'lineas']);
        LectorSimulado::marcarCorregido($destino->pedido->folio.':'.$destino->destino->codigo);
        $prep = Ubicacion::zona('preparacion');

        // Rastro en el historial: qué se movió entre bultos.
        foreach ($destino->lineas->filter(fn ($l) => $l->cantidad_leida !== null && $l->cantidad_leida !== $l->cantidad_separada) as $l) {
            $tarea = $destino->pedido->tareas()->where('producto_id', $l->producto_id)->first();
            if ($tarea) {
                $inventario->mover('separacion', $l->producto_id, $tarea->pedimento_id,
                    ['ubicacion_id' => $prep->id, 'estado' => 'en_preparacion'], ['ubicacion_id' => $prep->id, 'estado' => 'en_preparacion'],
                    abs($l->cantidad_leida - $l->cantidad_separada), $destino->pedido, $destino->pedido->folio,
                    'Corrección de bulto '.$destino->destino->codigo.': leídas '.$l->cantidad_leida.', debían ser '.$l->cantidad_separada);
            }
        }

        return back()->with('ok', 'Corrección registrada. Vuelve a leer el bulto de '.$destino->destino->codigo.'.');
    }

    /** Acepta lo que leyó el RFID como cantidad final y deja la diferencia como incidencia. */
    public function aceptarLectura(Request $request, PedidoDestino $destino)
    {
        $destino->load(['pedido', 'destino', 'lineas']);
        DB::transaction(function () use ($destino, $request) {
            foreach ($destino->lineas as $l) {
                if ($l->cantidad_leida === null || $l->cantidad_leida === $l->cantidad_separada) {
                    continue;
                }
                $dif = $l->cantidad_leida - $l->cantidad_separada;
                Incidencia::create([
                    'folio' => Incidencia::siguienteFolio(),
                    'tipo' => $dif < 0 ? 'faltante' : 'sobrante',
                    'origen_type' => 'pedido',
                    'origen_id' => $destino->pedido_id,
                    'origen_folio' => $destino->pedido->folio,
                    'producto_id' => $l->producto_id,
                    'destino_id' => $destino->destino_id,
                    'cantidad' => abs($dif),
                    'descripcion' => 'Revisión RFID de '.$destino->destino->codigo.': '.($dif < 0 ? 'faltan ' : 'sobran ').abs($dif).' piezas.',
                    'reporto_id' => $request->user()->id,
                ]);
                $l->update(['cantidad_separada' => $l->cantidad_leida]);
            }
            $destino->update(['estado' => 'verificado', 'verificado_at' => now()]);
        });
        $this->actualizarEstado($destino->pedido);

        return back()->with('ok', 'Se aceptó la lectura de '.$destino->destino->codigo.' y la diferencia quedó como incidencia.');
    }

    /** Salida: descuenta del inventario lo preparado y lo deja en el historial. */
    public function despachar(Request $request, Pedido $pedido, Inventario $inventario)
    {
        $pedido->load(['destinos.destino', 'lineas', 'tareas']);
        if ($pedido->estado !== 'listo') {
            return back()->with('error', 'Todos los destinos deben estar verificados antes de la salida.');
        }
        $pendientes = $pedido->destinos->reject->actividadesCompletas()->map(fn ($d) => $d->destino->codigo);
        if ($pendientes->isNotEmpty()) {
            return back()->with('error', 'Faltan actividades de preparación en: '.$pendientes->implode(', ').'.');
        }

        $datos = $request->validate([
            'transportista' => ['required', 'string', 'max:80'],
            'placas' => ['required', 'string', 'max:15'],
            'sello' => ['required', 'string', 'max:20'],
        ]);

        $prep = Ubicacion::zona('preparacion');
        $piezas = 0;

        DB::transaction(function () use ($pedido, $datos, $request, $inventario, $prep, &$piezas) {
            $salida = Salida::create($datos + [
                'folio' => sprintf('SAL-%04d', (Salida::max('id') ?? 0) + 1),
                'pedido_id' => $pedido->id,
                'piezas' => 0,
                'autorizo_id' => $request->user()->id,
            ]);

            $porProducto = $pedido->lineas->groupBy('producto_id')->map->sum('cantidad_separada');
            foreach ($porProducto as $productoId => $cantidad) {
                // Sale de los pedimentos que se tomaron para este pedido, en el mismo orden.
                foreach ($pedido->tareas->where('producto_id', $productoId)->pluck('pedimento_id')->unique() as $pedimentoId) {
                    if ($cantidad <= 0) {
                        break;
                    }
                    $hay = (int) Existencia::where(['producto_id' => $productoId, 'pedimento_id' => $pedimentoId,
                        'ubicacion_id' => $prep->id, 'estado' => 'en_preparacion'])->value('cantidad');
                    $sale = min($hay, $cantidad);
                    if ($sale > 0) {
                        $inventario->mover('salida', $productoId, $pedimentoId,
                            ['ubicacion_id' => $prep->id, 'estado' => 'en_preparacion'], null, $sale, $salida, $salida->folio,
                            'Sello '.$datos['sello'].' · '.$datos['transportista']);
                        $cantidad -= $sale;
                        $piezas += $sale;
                    }
                }
            }

            $salida->update(['piezas' => $piezas]);
            $pedido->update(['estado' => 'despachado']);
        });

        return back()->with('ok', "Salida registrada: {$piezas} piezas descontadas del inventario.");
    }

    private function actualizarEstado(Pedido $pedido): void
    {
        $estados = $pedido->destinos()->pluck('estado');
        $pedido->update(['estado' => $estados->every(fn ($e) => $e === 'verificado') ? 'listo' : 'en_revision']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Destino;
use App\Models\Existencia;
use App\Models\Incidencia;
use App\Models\Pedido;
use App\Models\PedidoDestino;
use App\Models\Producto;
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

    /** Captura manual: matriz de SKU por destino, como el reparto que prepara la responsable. */
    public function create(): Response
    {
        $surtibles = Ubicacion::whereIn('zona', ['rack', 'adicional'])->pluck('id');
        $disponible = Existencia::where('estado', 'disponible')->whereIn('ubicacion_id', $surtibles)
            ->selectRaw('producto_id, sum(cantidad) as piezas')->groupBy('producto_id')->pluck('piezas', 'producto_id');

        return Inertia::render('Pedidos/Create', [
            'productos' => Producto::orderBy('sku')->get()->map(fn ($p) => [
                ...Presentar::producto($p),
                'disponible' => (int) ($disponible[$p->id] ?? 0),
            ]),
            'destinos' => Destino::orderBy('codigo')->get()->map(fn ($d) => [
                'id' => $d->id, 'codigo' => $d->codigo, 'nombre' => $d->nombre, 'tipo' => $d->tipo,
                'requiere_etiqueta_comercial' => $d->requiere_etiqueta_comercial, 'requiere_sensor' => $d->requiere_sensor,
            ]),
            'propietarios' => Pedido::distinct()->orderBy('propietario')->pluck('propietario'),
            'manana' => now()->addDay()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'propietario' => ['required', 'string', 'max:120'],
            'fecha_compromiso' => ['required', 'date'],
            'celdas' => ['required', 'array', 'min:1', 'max:2000'],
            'celdas.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'celdas.*.destino_id' => ['required', 'integer', 'exists:destinos,id'],
            'celdas.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'propietario.required' => 'Indica de quién es la mercancía.',
            'fecha_compromiso.required' => 'Indica la fecha compromiso.',
            'celdas.required' => 'Captura al menos una cantidad para un destino.',
            'celdas.min' => 'Captura al menos una cantidad para un destino.',
            'celdas.*.cantidad.min' => 'Las cantidades deben ser mayores a cero.',
            'celdas.*.cantidad.integer' => 'Las cantidades deben ser números enteros.',
        ]);

        $pares = collect($datos['celdas'])->map(fn ($c) => $c['producto_id'].'-'.$c['destino_id']);
        if ($pares->duplicates()->isNotEmpty()) {
            return back()->withErrors(['celdas' => 'Hay un SKU repetido para el mismo destino.']);
        }

        $pedido = DB::transaction(function () use ($datos) {
            $pedido = Pedido::create([
                'folio' => sprintf('PED-%04d', (Pedido::max('id') ?? 0) + 1),
                'propietario' => $datos['propietario'],
                'fecha_compromiso' => $datos['fecha_compromiso'],
            ]);
            foreach (collect($datos['celdas'])->groupBy('destino_id') as $destinoId => $celdas) {
                $pd = $pedido->destinos()->create(['destino_id' => (int) $destinoId]);
                foreach ($celdas as $c) {
                    $pd->lineas()->create(['pedido_id' => $pedido->id, 'producto_id' => (int) $c['producto_id'], 'cantidad' => (int) $c['cantidad']]);
                }
            }

            return $pedido;
        });

        return redirect('/pedidos/'.$pedido->id)->with('ok', 'Pedido '.$pedido->folio.' creado. Revísalo y libéralo a surtido.');
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
        $pedido->load(['destinos.destino', 'destinos.verifico', 'destinos.lineas.producto', 'tareas.producto', 'tareas.pedimento', 'tareas.ubicacion', 'salida.autorizo']);

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
                'verificacion' => $d->verificacion,
                'verifico' => $d->verifico?->name,
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
    public function leerDestino(Request $request, PedidoDestino $destino, LectorRfid $lector)
    {
        $pedido = $destino->pedido;
        abort_unless(in_array($pedido->estado, ['en_revision', 'listo']), 422);
        $destino->load(['lineas', 'destino']);

        $esperado = $destino->lineas->groupBy('producto_id')->map->sum('cantidad_separada')->all();
        $conteo = Epc::contarPorProducto($lector->leer($esperado, $pedido->folio.':'.$destino->destino->codigo));

        return $this->evaluarBulto($destino, fn ($l) => $conteo[$l->producto_id] ?? 0, 'rfid', $request->user()->id);
    }

    /** Revisión del bulto sin lector: se captura lo contado por SKU. */
    public function contarDestino(Request $request, PedidoDestino $destino)
    {
        abort_unless(in_array($destino->pedido->estado, ['en_revision', 'listo']), 422);
        $destino->load(['lineas', 'destino']);
        $datos = $request->validate([
            'lineas' => ['required', 'array'],
            'lineas.*' => ['required', 'integer', 'min:0', 'max:100000'],
        ], ['lineas.*.*' => 'Captura lo contado de cada SKU (0 o más).']);
        if ($destino->lineas->contains(fn ($l) => ! array_key_exists($l->id, $datos['lineas']))) {
            return back()->with('error', 'Captura lo contado de todos los SKU del destino.');
        }

        return $this->evaluarBulto($destino, fn ($l) => (int) $datos['lineas'][$l->id], 'manual', $request->user()->id);
    }

    private function evaluarBulto(PedidoDestino $destino, \Closure $contado, string $metodo, int $userId)
    {
        $cuadra = true;
        foreach ($destino->lineas as $l) {
            $leida = $contado($l);
            $l->update(['cantidad_leida' => $leida]);
            $cuadra = $cuadra && $leida === $l->cantidad_separada;
        }

        $destino->update(($cuadra
            ? ['estado' => 'verificado', 'verificado_at' => now()]
            : ['estado' => 'con_diferencia', 'verificado_at' => null]) + ['verificacion' => $metodo, 'verifico_id' => $userId]);
        $this->actualizarEstado($destino->pedido);

        $como = $metodo === 'rfid' ? '' : ' (conteo a mano)';

        return back()->with($cuadra ? 'ok' : 'error', $cuadra
            ? $destino->destino->codigo.' cuadra con lo preparado'.$como.'.'
            : $destino->destino->codigo.' no cuadra'.$como.': revisa las líneas marcadas.');
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

    /** Marca o desmarca una actividad de preparación (etiquetas, sensor) sin tocar cantidades ni la verificación. */
    public function actividad(Request $request, PedidoDestino $destino)
    {
        $destino->load(['pedido', 'destino']);
        abort_if($destino->pedido->estado === 'despachado', 422);
        $datos = $request->validate([
            'campo' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys($destino->actividades()))],
            'hecha' => ['required', 'boolean'],
        ]);
        $destino->update([$datos['campo'] => $datos['hecha']]);

        return back()->with('ok', $destino->actividades()[$datos['campo']].' de '.$destino->destino->codigo.($datos['hecha'] ? ' marcada.' : ' desmarcada.'));
    }

    /** Salida: descuenta del inventario lo preparado y lo deja en el historial. */
    public function despachar(Request $request, Pedido $pedido, Inventario $inventario)
    {
        $pedido->load(['destinos.destino', 'lineas', 'tareas']);
        if ($pedido->estado !== 'listo' || $pedido->destinos->contains(fn ($d) => $d->estado !== 'verificado')) {
            return back()->with('error', 'Todos los destinos deben estar verificados antes de la salida.');
        }
        $pendientes = $pedido->destinos->reject->actividadesCompletas()->map(fn ($d) => $d->destino->codigo);
        if ($pendientes->isNotEmpty()) {
            return back()->with('error', 'Faltan actividades de preparación en: '.$pendientes->implode(', ').'. Márcalas en cada destino del pedido.');
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

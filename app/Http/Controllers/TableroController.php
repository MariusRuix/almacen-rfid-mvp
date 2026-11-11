<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Incidencia;
use App\Models\Movimiento;
use App\Models\Pedido;
use App\Models\Recepcion;
use Inertia\Inertia;
use Inertia\Response;

class TableroController extends Controller
{
    public function __invoke(): Response
    {
        $porEstado = Existencia::selectRaw('estado, sum(cantidad) as piezas')->groupBy('estado')->pluck('piezas', 'estado');

        $porPedimento = Existencia::with('pedimento')
            ->selectRaw('pedimento_id, sum(cantidad) as piezas, count(distinct producto_id) as skus')
            ->groupBy('pedimento_id')->get()
            ->map(fn ($e) => [
                'pedimento' => Presentar::pedimento($e->pedimento),
                'piezas' => (int) $e->piezas,
                'skus' => (int) $e->skus,
            ])->sortBy('pedimento.fecha_pago')->values();

        return Inertia::render('Tablero', [
            'existencias' => [
                'disponible' => (int) ($porEstado['disponible'] ?? 0),
                'en_preparacion' => (int) ($porEstado['en_preparacion'] ?? 0),
                'incidencia' => (int) ($porEstado['incidencia'] ?? 0),
            ],
            'porPedimento' => $porPedimento,
            'recepciones' => Recepcion::with('pedimento')->withSum('lineas', 'piezas_esperadas')
                ->where('estado', '!=', 'cerrada')->orWhereHas('lineas', fn ($q) => $q->whereNull('ubicada_at'))
                ->latest('id')->take(4)->get()->map(fn ($r) => [
                    'id' => $r->id, 'folio' => $r->folio, 'estado' => $r->estado,
                    'pedimento' => Presentar::pedimento($r->pedimento),
                    'piezas' => (int) $r->lineas_sum_piezas_esperadas,
                ]),
            'pedidos' => Pedido::withCount('destinos')->withSum('lineas', 'cantidad')
                ->where('estado', '!=', 'despachado')->latest('id')->take(4)->get()->map(fn ($p) => [
                    'id' => $p->id, 'folio' => $p->folio, 'estado' => $p->estado,
                    'destinos' => $p->destinos_count, 'piezas' => (int) $p->lineas_sum_cantidad,
                    'fecha_compromiso' => $p->fecha_compromiso->toDateString(),
                ]),
            'incidencias' => Incidencia::with('producto')->where('estado', 'abierta')->latest('id')->take(5)->get()
                ->map(fn ($i) => [
                    'id' => $i->id, 'folio' => $i->folio, 'tipo' => $i->tipo, 'cantidad' => $i->cantidad,
                    'origen_folio' => $i->origen_folio, 'producto' => Presentar::producto($i->producto),
                ]),
            'ultimosMovimientos' => Movimiento::with(['producto', 'origen', 'destino', 'usuario'])->latest('id')->take(6)->get()
                ->map(fn ($m) => MovimientoController::fila($m)),
        ]);
    }
}

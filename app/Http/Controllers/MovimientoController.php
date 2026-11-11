<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $request->only(['tipo', 'buscar', 'folio']);

        $pagina = Movimiento::with(['producto', 'pedimento', 'origen', 'destino', 'usuario'])
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($f['folio'] ?? null, fn ($q, $v) => $q->where('referencia_folio', $v))
            ->when($f['buscar'] ?? null, fn ($q, $b) => $q->whereHas('producto', fn ($p) => $p->where('sku', 'like', "%{$b}%")))
            ->latest('id')->paginate(40)->withQueryString();

        return Inertia::render('Movimientos/Index', [
            'movimientos' => $pagina->through(fn ($m) => self::fila($m)),
            'filtros' => (object) $f,
        ]);
    }

    public static function fila(Movimiento $m): array
    {
        return [
            'id' => $m->id,
            'tipo' => $m->tipo,
            'fecha' => $m->created_at?->toDateTimeString(),
            'producto' => Presentar::producto($m->producto),
            'pedimento' => $m->pedimento?->numero,
            'origen' => $m->origen?->codigo,
            'estado_origen' => $m->estado_origen,
            'destino' => $m->destino?->codigo,
            'estado_destino' => $m->estado_destino,
            'cantidad' => $m->cantidad,
            'folio' => $m->referencia_folio,
            'usuario' => $m->usuario?->name,
            'nota' => $m->nota,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Support\Expediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $request->only(['tipo', 'buscar', 'folio', 'fotos']);

        $pagina = Movimiento::with(['producto', 'pedimento', 'origen', 'destino', 'usuario'])
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($f['folio'] ?? null, fn ($q, $v) => $q->where('referencia_folio', $v))
            ->when($f['buscar'] ?? null, fn ($q, $b) => $q->whereHas('producto', fn ($p) => $p->where('sku', 'like', "%{$b}%")))
            ->when($request->boolean('fotos'), function ($q) {
                // Solo movimientos cuyo documento (recepción, pedido, salida o incidencia) tiene fotos.
                $docs = Expediente::documentosConFotos();
                $q->where(function ($w) use ($docs) {
                    $w->whereRaw('1 = 0');
                    foreach ($docs as $tipo => $ids) {
                        if ($ids) {
                            $w->orWhere(fn ($x) => $x->where('referencia_type', $tipo)->whereIn('referencia_id', $ids));
                        }
                    }
                });
            })
            ->latest('id')->paginate(40)->withQueryString();

        $fotos = Expediente::conteos($pagina->items());

        return Inertia::render('Movimientos/Index', [
            'movimientos' => $pagina->through(fn ($m) => self::fila($m, $fotos[$m->id] ?? 0)),
            'filtros' => (object) $f,
        ]);
    }

    /** Fotos del documento del movimiento, para el panel lateral del historial. */
    public function evidencias(Movimiento $movimiento): JsonResponse
    {
        $raiz = Expediente::raiz($movimiento->referencia_type, $movimiento->referencia_id);

        return response()->json([
            'documento' => Expediente::documento($movimiento),
            'grupos' => $raiz ? Expediente::grupos(Expediente::evidencias(...$raiz)) : [],
        ]);
    }

    public static function fila(Movimiento $m, ?int $fotos = null): array
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
            'fotos' => $fotos,
        ];
    }
}

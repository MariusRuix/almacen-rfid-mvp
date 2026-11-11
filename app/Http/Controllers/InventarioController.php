<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Pedimento;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventarioController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $request->only(['buscar', 'pedimento_id', 'ubicacion_id', 'estado']);

        $existencias = Existencia::with(['producto', 'pedimento', 'ubicacion'])
            ->when($f['buscar'] ?? null, fn ($q, $b) => $q->whereHas('producto', fn ($p) => $p
                ->where('sku', 'like', "%{$b}%")->orWhere('descripcion', 'like', "%{$b}%")->orWhere('referencia_interna', 'like', "%{$b}%")))
            ->when($f['pedimento_id'] ?? null, fn ($q, $v) => $q->where('pedimento_id', $v))
            ->when($f['ubicacion_id'] ?? null, fn ($q, $v) => $q->where('ubicacion_id', $v))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->where('cantidad', '>', 0)
            ->get();

        // Agrupado por SKU, con el desglose por pedimento / ubicación / estado debajo.
        $grupos = $existencias->groupBy('producto_id')->map(function ($filas) {
            $p = $filas->first()->producto;

            return [
                'producto' => Presentar::producto($p),
                'total' => $filas->sum('cantidad'),
                'por_estado' => [
                    'disponible' => $filas->where('estado', 'disponible')->sum('cantidad'),
                    'en_preparacion' => $filas->where('estado', 'en_preparacion')->sum('cantidad'),
                    'incidencia' => $filas->where('estado', 'incidencia')->sum('cantidad'),
                ],
                'pedimentos' => $filas->pluck('pedimento_id')->unique()->count(),
                'detalle' => $filas->sortBy([fn ($a, $b) => $a->pedimento->fecha_pago <=> $b->pedimento->fecha_pago,
                    fn ($a, $b) => strcmp($a->ubicacion->codigo, $b->ubicacion->codigo)])
                    ->values()->map(fn ($e) => [
                        'id' => $e->id,
                        'pedimento' => Presentar::pedimento($e->pedimento),
                        'ubicacion' => Presentar::ubicacion($e->ubicacion),
                        'estado' => $e->estado,
                        'cantidad' => $e->cantidad,
                    ]),
            ];
        })->sortBy('producto.sku')->values();

        return Inertia::render('Inventario/Index', [
            'grupos' => $grupos,
            'filtros' => (object) $f,
            'totales' => [
                'piezas' => $existencias->sum('cantidad'),
                'skus' => $existencias->pluck('producto_id')->unique()->count(),
                'ubicaciones' => $existencias->pluck('ubicacion_id')->unique()->count(),
            ],
            'pedimentos' => Pedimento::orderBy('fecha_pago')->get()->map(fn ($p) => Presentar::pedimento($p)),
            'ubicaciones' => Ubicacion::orderBy('codigo')->get(['id', 'codigo', 'zona']),
        ]);
    }

    public function mapa(): Response
    {
        $ocupacion = Existencia::selectRaw('ubicacion_id, sum(cantidad) as piezas, count(distinct pedimento_id) as pedimentos')
            ->groupBy('ubicacion_id')->get()->keyBy('ubicacion_id');

        return Inertia::render('Inventario/Mapa', [
            'ubicaciones' => Ubicacion::orderBy('codigo')->get()->map(fn ($u) => [
                'id' => $u->id, 'codigo' => $u->codigo, 'zona' => $u->zona, 'capacidad' => $u->capacidad,
                'piezas' => (int) ($ocupacion[$u->id]->piezas ?? 0),
                'pedimentos' => (int) ($ocupacion[$u->id]->pedimentos ?? 0),
            ]),
        ]);
    }
}

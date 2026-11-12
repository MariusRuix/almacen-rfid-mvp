<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Existencia;
use App\Models\Movimiento;
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
        $existencias = Existencia::with(['producto', 'pedimento'])->where('cantidad', '>', 0)->get();
        $porUbicacion = $existencias->groupBy('ubicacion_id');
        $conHistorial = Movimiento::pluck('ubicacion_origen_id')->merge(Movimiento::pluck('ubicacion_destino_id'))->filter()->unique()->flip();

        // Últimos movimientos de cada ubicación (para el panel lateral)
        $recientes = Movimiento::with(['producto', 'usuario', 'origen', 'destino'])->latest('id')->take(400)->get();

        $ubicaciones = Ubicacion::with('area')->orderBy('codigo')->get();

        return Inertia::render('Inventario/Mapa', [
            'areas' => Area::orderBy('orden')->orderBy('id')->get()->map(function (Area $a) use ($ubicaciones, $porUbicacion) {
                $mias = $ubicaciones->where('area_id', $a->id);

                return [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'descripcion' => $a->descripcion,
                    'activa' => $a->activa,
                    'ubicaciones' => $mias->count(),
                    'piezas' => (int) $mias->sum(fn ($u) => ($porUbicacion[$u->id] ?? collect())->sum('cantidad')),
                    'capacidad' => (int) $mias->where('activa', true)->sum('capacidad'),
                    'tiene_operativas' => $mias->contains(fn ($u) => in_array($u->zona, Ubicacion::OPERATIVAS, true)),
                ];
            }),
            'ubicaciones' => $ubicaciones->map(function ($u) use ($porUbicacion, $conHistorial, $recientes) {
                $filas = $porUbicacion[$u->id] ?? collect();

                return [
                    'id' => $u->id,
                    'codigo' => $u->codigo,
                    'zona' => $u->zona,
                    'capacidad' => $u->capacidad,
                    'activa' => $u->activa,
                    'area_id' => $u->area_id,
                    'disponible' => $u->disponible(),
                    'piezas' => (int) $filas->sum('cantidad'),
                    'pedimentos' => $filas->pluck('pedimento_id')->unique()->count(),
                    'incidencia' => $filas->contains('estado', 'incidencia'),
                    'usada' => isset($conHistorial[$u->id]) || $filas->isNotEmpty(),
                    'contenido' => $filas->sortBy('producto.sku')->values()->map(fn ($e) => [
                        'existencia_id' => $e->id,
                        'sku' => $e->producto->sku,
                        'descripcion' => $e->producto->descripcion,
                        'pedimento' => $e->pedimento->numero,
                        'fecha_pago' => $e->pedimento->fecha_pago->toDateString(),
                        'estado' => $e->estado,
                        'cantidad' => $e->cantidad,
                    ]),
                    'movimientos' => $recientes->filter(fn ($m) => $m->ubicacion_origen_id === $u->id || $m->ubicacion_destino_id === $u->id)
                        ->take(5)->values()->map(fn ($m) => MovimientoController::fila($m)),
                ];
            }),
        ]);
    }
}

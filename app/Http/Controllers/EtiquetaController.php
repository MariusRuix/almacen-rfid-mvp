<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Recepcion;
use App\Models\RecepcionCaja;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Etiquetas para imprimir y pegar: caja (recepción), bulto (separación) y rótulo de ubicación.
 * En la demo se imprimen desde el navegador; en operación irían a una impresora térmica (ZPL).
 */
class EtiquetaController extends Controller
{
    private const ORIGEN = ['rfid' => 'Lector RFID', 'codigo' => 'Código de barras', 'manual' => 'Conteo a mano'];

    /** Una etiqueta por caja contada. ?caja= limita a una sola. */
    public function cajas(Request $request, Recepcion $recepcion): View
    {
        $recepcion->load(['pedimento', 'lineas', 'cajas.lineas.producto']);
        $cajas = $recepcion->cajas->where('estado', 'leida')->sortBy('numero')
            ->when($request->integer('caja'), fn ($c, $id) => $c->where('id', $id));
        $totalCajas = max((int) $recepcion->lineas->sum('cajas_esperadas'), $recepcion->cajas->where('estado', 'leida')->count());

        $etiquetas = $cajas->values()->map(fn (RecepcionCaja $c) => [
            'tipo' => 'caja',
            'codigo' => self::folioCaja($recepcion, $c),
            'kicker' => 'Caja · Recepción '.$recepcion->folio,
            'grande' => 'C'.str_pad((string) $c->numero, 2, '0', STR_PAD_LEFT),
            'grande_nota' => 'de '.$totalCajas.' esperadas',
            'contenido' => $c->lineas->map(fn ($l) => [
                'sku' => $l->producto->sku,
                'descripcion' => $l->producto->descripcion,
                'piezas' => $l->piezas_leidas + $l->sin_etiqueta,
                'avisos' => array_filter([
                    $l->danadas ? $l->danadas.($l->danadas === 1 ? ' dañada' : ' dañadas') : null,
                    $l->sin_accesorio ? $l->sin_accesorio.' sin '.($l->producto->accesorio ?? 'accesorio') : null,
                ]),
            ])->all(),
            'campos' => [
                ['Pedimento', $recepcion->pedimento->numero, true],
                ['Contenedor', self::contenedor($recepcion->pedimento->contenedor), true],
                ['Contada', $c->created_at->format('d/m/Y H:i'), false],
                ['Cómo se contó', self::ORIGEN[$c->origen] ?? $c->origen, false],
            ],
        ]);

        return $this->vista('Etiquetas de caja · '.$recepcion->folio, $etiquetas->all(), 'caja', $request,
            $etiquetas->isEmpty() ? 'Esta recepción todavía no tiene cajas contadas. Las etiquetas salen del conteo caja por caja en la vista del operador.' : null);
    }

    /** Una etiqueta por bulto de cada destino separado. ?destino= limita a uno. */
    public function bultos(Request $request, Pedido $pedido): View
    {
        $pedido->load(['destinos.destino', 'destinos.lineas.producto']);
        $destinos = $pedido->destinos->where('bultos', '>', 0)
            ->when($request->integer('destino'), fn ($d, $id) => $d->where('id', $id));

        $etiquetas = [];
        foreach ($destinos as $d) {
            $requisitos = array_values(array_filter([
                $d->destino->requiere_etiqueta_comercial ? 'Etiqueta comercial' : null,
                $d->destino->requiere_sensor ? 'Sensor' : null,
            ]));
            $contenido = $d->lineas->map(fn ($l) => [
                'sku' => $l->producto->sku,
                'descripcion' => $l->producto->descripcion,
                'piezas' => $l->cantidad_separada ?? $l->cantidad,
                'avisos' => [],
            ])->all();
            for ($b = 1; $b <= $d->bultos; $b++) {
                $etiquetas[] = [
                    'tipo' => 'bulto',
                    'codigo' => $pedido->folio.'-'.$d->destino->codigo.'-B'.$b,
                    'kicker' => 'Bulto · Pedido '.$pedido->folio,
                    'grande' => $d->destino->codigo,
                    'grande_nota' => $d->destino->nombre,
                    'bulto' => ['n' => $b, 'de' => $d->bultos],
                    'contenido' => $contenido,
                    'contenido_titulo' => $d->bultos > 1 ? 'Contenido del destino (entre los '.$d->bultos.' bultos)' : 'Contenido',
                    'campos' => [
                        ['Propietario', $pedido->propietario, false],
                        ['Compromiso', $pedido->fecha_compromiso?->format('d/m/Y') ?? '—', false],
                        ['Lleva', $requisitos ? implode(' · ', $requisitos) : 'Solo etiqueta de importación', false],
                    ],
                ];
            }
        }

        return $this->vista('Etiquetas de bulto · '.$pedido->folio, $etiquetas, 'bulto', $request,
            $etiquetas ? null : 'Todavía no hay destinos separados. Las etiquetas de bulto salen cuando el operador confirma un destino con sus bultos.');
    }

    /** Rótulos de ubicación: ?codigo=A-01-3, ?area=1 o ?area=1&pasillo=A. */
    public function ubicaciones(Request $request): View
    {
        $q = Ubicacion::with('area')->where('activa', true)->whereIn('zona', Ubicacion::ALMACENAJE);
        if ($request->filled('codigo')) {
            $q = Ubicacion::with('area')->where('codigo', $request->string('codigo'));
        } else {
            $q->when($request->integer('area'), fn ($q, $a) => $q->where('area_id', $a))
                ->when($request->filled('pasillo'), fn ($q) => $q->where('codigo', 'like', $request->string('pasillo').'-%'));
        }
        $zonas = ['rack' => 'Rack', 'estanteria' => 'Estantería', 'piso' => 'Piso', 'adicional' => 'Adicional',
            'recepcion' => 'Recepción', 'preparacion' => 'Preparación', 'incidencia' => 'Incidencias'];

        $etiquetas = $q->orderBy('codigo')->get()->map(function (Ubicacion $u) use ($zonas) {
            $partes = explode('-', $u->codigo);

            return [
                'tipo' => 'ubicacion',
                'codigo' => $u->codigo,
                'kicker' => ($u->area?->nombre ?? 'Almacén').' · '.($zonas[$u->zona] ?? $u->zona),
                'grande' => $u->codigo,
                'partes' => count($partes) === 3 ? ['Pasillo' => $partes[0], 'Módulo' => $partes[1], 'Nivel' => $partes[2]] : [],
            ];
        });

        return $this->vista('Rótulos de ubicación', $etiquetas->all(), 'ubicacion', $request,
            $etiquetas->isEmpty() ? 'No hay ubicaciones activas con ese filtro.' : null);
    }

    public static function folioCaja(Recepcion $recepcion, RecepcionCaja $caja): string
    {
        return $recepcion->folio.'-C'.str_pad((string) $caja->numero, 2, '0', STR_PAD_LEFT);
    }

    private static function contenedor(?string $c): string
    {
        return $c && strlen($c) === 11 ? substr($c, 0, 4).' '.substr($c, 4, 6).' '.substr($c, 10) : (string) $c;
    }

    private function vista(string $titulo, array $etiquetas, string $tipo, Request $request, ?string $vacio): View
    {
        return view('etiquetas', [
            'titulo' => $titulo,
            'etiquetas' => $etiquetas,
            'tipo' => $tipo,
            'formato' => $request->query('formato') === 'carta' ? 'carta' : 'termica',
            'vacio' => $vacio,
        ]);
    }
}

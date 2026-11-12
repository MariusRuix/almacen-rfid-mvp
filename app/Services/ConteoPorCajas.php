<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Recepcion;
use App\Models\RecepcionCaja;
use App\Models\RecepcionCajaLinea;
use App\Services\Rfid\Epc;
use App\Services\Rfid\LectorRfid;
use Illuminate\Support\Facades\DB;

/**
 * Recepción caja por caja. Cada caja se lee por separado; el total de la recepción
 * es la suma de sus cajas (las anuladas no cuentan).
 */
class ConteoPorCajas
{
    public function __construct(private LectorRfid $lector) {}

    /** Lee la siguiente caja. Devuelve null si ya no hay cajas por leer. */
    public function leerCaja(Recepcion $recepcion, int $userId): ?RecepcionCaja
    {
        $contenido = $this->siguienteContenido($recepcion);
        if (! $contenido) {
            return null;
        }

        return DB::transaction(function () use ($recepcion, $contenido, $userId) {
            $numero = (int) $recepcion->cajas()->max('numero') + 1;
            $caja = $recepcion->cajas()->create(['numero' => $numero, 'user_id' => $userId]);
            $conteo = Epc::contarPorProducto($this->lector->leer($contenido, $recepcion->folio.':caja-'.$numero));
            foreach ($conteo as $productoId => $piezas) {
                $caja->lineas()->create(['producto_id' => $productoId, 'piezas_leidas' => $piezas]);
            }
            $this->recalcular($recepcion);

            return $caja;
        });
    }

    /** Caja contada sin lector RFID: a mano o escaneando el código de barras de cada pieza. */
    public function registrarManual(Recepcion $recepcion, int $productoId, int $piezas, string $origen, int $userId): RecepcionCaja
    {
        return DB::transaction(function () use ($recepcion, $productoId, $piezas, $origen, $userId) {
            $numero = (int) $recepcion->cajas()->max('numero') + 1;
            $caja = $recepcion->cajas()->create(['numero' => $numero, 'user_id' => $userId, 'origen' => $origen]);
            $caja->lineas()->create(['producto_id' => $productoId, 'piezas_leidas' => $piezas]);
            $this->recalcular($recepcion);

            return $caja;
        });
    }

    /** Segunda lectura de una caja ya corregida físicamente: sale completa. */
    public function releerCaja(RecepcionCaja $caja): void
    {
        $recepcion = $caja->recepcion()->with('lineas')->first();
        $porProducto = $recepcion->lineas->keyBy('producto_id');
        DB::transaction(function () use ($caja, $porProducto, $recepcion) {
            foreach ($caja->lineas as $l) {
                $esperada = $porProducto[$l->producto_id] ?? null;
                if ($esperada && $esperada->piezas_esperadas > 0) {
                    $l->update(['piezas_leidas' => $esperada->piezas_por_caja]);
                }
            }
            $caja->update(['estado' => 'leida']);
            $this->recalcular($recepcion);
        });
    }

    /** Pasa lo contado en las cajas a los renglones de la recepción. */
    public function recalcular(Recepcion $recepcion): void
    {
        $recepcion->load('lineas');
        // piezas_leidas de una caja = lo contado con el método de esa caja; "leídas RFID" solo suma las cajas RFID.
        $suma = RecepcionCajaLinea::join('recepcion_cajas', 'recepcion_cajas.id', '=', 'recepcion_caja_lineas.recepcion_caja_id')
            ->where('recepcion_cajas.recepcion_id', $recepcion->id)->where('recepcion_cajas.estado', 'leida')
            ->selectRaw("producto_id, sum(case when recepcion_cajas.origen = 'rfid' then piezas_leidas else 0 end) as rfid,
                sum(piezas_leidas) as leidas, sum(sin_etiqueta) as sin_etiqueta, sum(sin_accesorio) as sin_accesorio, sum(danadas) as danadas")
            ->groupBy('producto_id')->get()->keyBy('producto_id');

        foreach ($recepcion->lineas as $linea) {
            $s = $suma[$linea->producto_id] ?? null;
            $linea->update([
                'piezas_leidas' => (int) ($s->rfid ?? 0),
                'piezas_confirmadas' => (int) ($s->leidas ?? 0) + (int) ($s->sin_etiqueta ?? 0),
                'piezas_sin_etiqueta' => (int) ($s->sin_etiqueta ?? 0),
                'piezas_sin_accesorio' => (int) ($s->sin_accesorio ?? 0),
                'piezas_danadas' => (int) ($s->danadas ?? 0),
            ]);
        }

        // Un SKU que apareció en una caja sin venir en el archivo: renglón no esperado.
        foreach ($suma->except($recepcion->lineas->pluck('producto_id')->all()) as $productoId => $s) {
            $recepcion->lineas()->create([
                'producto_id' => $productoId, 'cajas_esperadas' => 0, 'piezas_esperadas' => 0,
                'piezas_por_caja' => Producto::find($productoId)->piezas_por_caja,
                'piezas_leidas' => (int) $s->rfid, 'piezas_confirmadas' => (int) $s->leidas + (int) $s->sin_etiqueta,
                'piezas_sin_etiqueta' => (int) $s->sin_etiqueta, 'piezas_sin_accesorio' => (int) $s->sin_accesorio,
                'piezas_danadas' => (int) $s->danadas,
            ]);
        }

        // Cualquier cambio en las cajas reabre el conteo (si ya estaba terminado, hay que volver a terminarlo).
        if (in_array($recepcion->estado, ['esperada', 'contada'], true)) {
            $recepcion->update(['estado' => 'en_conteo', 'conteo_terminado_at' => null, 'termino_conteo_id' => null]);
        }
    }

    /**
     * Simulación: qué hay en la siguiente caja. Se asume una caja por SKU (cajas × piezas por caja).
     * El escenario de config/demo.php se aplica en la última caja de cada SKU; un SKU que no venía
     * en el archivo aparece en una caja extra al final.
     *
     * @return array<int,int>|null producto_id => piezas físicamente en la caja
     */
    private function siguienteContenido(Recepcion $recepcion): ?array
    {
        $recepcion->load(['lineas.producto', 'cajas.lineas']);
        $leidasPorProducto = $recepcion->cajas->where('estado', 'leida')
            ->flatMap->lineas->groupBy('producto_id')->map->count();
        $escenario = config('demo.escenarios_rfid.'.$recepcion->folio, []);

        foreach ($recepcion->lineas->where('cajas_esperadas', '>', 0) as $linea) {
            $hechas = $leidasPorProducto[$linea->producto_id] ?? 0;
            if ($hechas < $linea->cajas_esperadas) {
                $piezas = $linea->piezas_por_caja;
                if ($hechas === $linea->cajas_esperadas - 1) {
                    $piezas = max(0, $piezas + ($escenario[$linea->producto->sku] ?? 0));
                }

                return [$linea->producto_id => $piezas];
            }
        }

        // Todo lo esperado ya se leyó: ¿el escenario trae un SKU que no venía?
        $esperados = $recepcion->lineas->pluck('producto.sku')->all();
        foreach ($escenario as $sku => $piezas) {
            if (! in_array($sku, $esperados, true) && $piezas > 0) {
                $producto = Producto::where('sku', $sku)->first();
                if ($producto && ! isset($leidasPorProducto[$producto->id])) {
                    return [$producto->id => $piezas];
                }
            }
        }

        return null;
    }
}

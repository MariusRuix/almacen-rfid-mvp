<?php

namespace App\Services\Rfid;

use App\Models\Producto;

/**
 * Lector de demostración: devuelve lo esperado y aplica los escenarios de
 * config/demo.php (piezas que faltan o sobran) solo en la primera lectura de cada contexto.
 */
class LectorSimulado implements LectorRfid
{
    public function leer(array $esperado, string $contexto): array
    {
        $ajustes = session()->pull('rfid_releer.'.$contexto) ? [] : $this->ajustes($contexto);
        $productos = Producto::whereIn('id', array_keys($esperado + $ajustes))->get()->keyBy('id');

        $epcs = [];
        foreach ($productos as $id => $producto) {
            $n = max(0, ($esperado[$id] ?? 0) + ($ajustes[$id] ?? 0));
            for ($i = 1; $i <= $n; $i++) {
                $epcs[] = strtoupper($producto->epc_prefijo.str_pad(dechex(crc32($contexto) % 0xFFFF).dechex($i), 12, '0', STR_PAD_LEFT));
            }
        }

        return $epcs;
    }

    /** La siguiente lectura de este contexto será exacta (el operador ya corrigió físicamente). */
    public static function marcarCorregido(string $contexto): void
    {
        session()->put('rfid_releer.'.$contexto, true);
    }

    /** @return array<int,int> producto_id => ajuste */
    private function ajustes(string $contexto): array
    {
        $porSku = config('demo.escenarios_rfid.'.$contexto, []);
        if (! $porSku) {
            return [];
        }

        return Producto::whereIn('sku', array_keys($porSku))->get()
            ->mapWithKeys(fn ($p) => [$p->id => $porSku[$p->sku]])->all();
    }
}

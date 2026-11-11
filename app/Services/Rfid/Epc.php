<?php

namespace App\Services\Rfid;

use App\Models\Producto;

class Epc
{
    /**
     * Agrupa EPCs por producto usando el prefijo de 12 caracteres.
     *
     * @param  list<string>  $epcs
     * @return array<int,int> producto_id => piezas leídas
     */
    public static function contarPorProducto(array $epcs): array
    {
        $prefijos = Producto::pluck('id', 'epc_prefijo')->mapWithKeys(fn ($id, $p) => [strtoupper($p) => $id]);
        $conteo = [];
        foreach ($epcs as $epc) {
            $id = $prefijos[substr($epc, 0, 12)] ?? null;
            if ($id) {
                $conteo[$id] = ($conteo[$id] ?? 0) + 1;
            }
        }

        return $conteo;
    }
}

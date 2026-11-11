<?php

namespace App\Http\Controllers;

use App\Models\Pedimento;
use App\Models\Producto;
use App\Models\Ubicacion;

/** Formas compactas que comparten varias pantallas. */
class Presentar
{
    public static function producto(?Producto $p): ?array
    {
        return $p ? [
            'id' => $p->id,
            'sku' => $p->sku,
            'referencia' => $p->referencia_interna,
            'descripcion' => $p->descripcion,
            'accesorio' => $p->accesorio,
            'piezas_por_caja' => $p->piezas_por_caja,
        ] : null;
    }

    public static function pedimento(?Pedimento $p): ?array
    {
        return $p ? [
            'id' => $p->id,
            'numero' => $p->numero,
            'clave' => $p->clave,
            'aduana' => $p->aduana,
            'contenedor' => $p->contenedor,
            'proveedor' => $p->proveedor,
            'fecha_pago' => $p->fecha_pago?->toDateString(),
        ] : null;
    }

    public static function ubicacion(?Ubicacion $u): ?array
    {
        return $u ? ['id' => $u->id, 'codigo' => $u->codigo, 'zona' => $u->zona] : null;
    }
}

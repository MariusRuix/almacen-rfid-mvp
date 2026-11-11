<?php

namespace App\Support;

/** Dígito verificador ISO 6346 para números de contenedor (datos ficticios con formato real). */
class Contenedor
{
    public static function digito(string $base): int
    {
        $valores = [];
        $v = 10;
        foreach (range('A', 'Z') as $letra) {
            if ($v % 11 === 0) {
                $v++;
            }
            $valores[$letra] = $v++;
        }

        $suma = 0;
        foreach (str_split(strtoupper($base)) as $i => $c) {
            $n = ctype_digit($c) ? (int) $c : $valores[$c];
            $suma += $n * (2 ** $i);
        }

        return ($suma % 11) % 10;
    }

    public static function numero(string $dueno, int $serie): string
    {
        $base = strtoupper($dueno).str_pad((string) $serie, 6, '0', STR_PAD_LEFT);

        return $base.self::digito($base);
    }
}

<?php

namespace App\Support;

/**
 * Código de barras Code 128 (juego B) dibujado como SVG, sin dependencias.
 * Lo leen los escáneres de mano comunes; sirve para folios como REC-0001-C03 o A-01-3.
 */
class Code128
{
    /** Anchos barra/espacio de cada símbolo, del 0 al 106 (106 = parada). */
    private const PATRONES = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const INICIO_B = 104;

    private const PARADA = 106;

    /** Secuencia de módulos (1 = barra, 0 = espacio) del texto, con dígito de control. */
    public static function modulos(string $texto): string
    {
        $codigos = [self::INICIO_B];
        $suma = self::INICIO_B;
        foreach (str_split($texto) as $i => $c) {
            $v = ord($c) - 32;
            if ($v < 0 || $v > 95) {
                throw new \InvalidArgumentException("Carácter no válido para Code 128: {$c}");
            }
            $codigos[] = $v;
            $suma += $v * ($i + 1);
        }
        $codigos[] = $suma % 103;
        $codigos[] = self::PARADA;

        $bits = '';
        foreach ($codigos as $codigo) {
            foreach (str_split(self::PATRONES[$codigo]) as $j => $ancho) {
                $bits .= str_repeat($j % 2 === 0 ? '1' : '0', (int) $ancho);
            }
        }

        return $bits;
    }

    /** SVG escalable: el ancho lo da el contenedor; el alto, en unidades del viewBox. */
    public static function svg(string $texto, int $alto = 40): string
    {
        $bits = self::modulos($texto);
        $margen = 10; // zona en blanco que pide el estándar
        $ancho = strlen($bits) + 2 * $margen;
        $rects = '';
        $x = 0;
        while ($x < strlen($bits)) {
            if ($bits[$x] === '1') {
                $n = strspn($bits, '1', $x);
                $rects .= '<rect x="'.($x + $margen).'" y="0" width="'.$n.'" height="'.$alto.'"/>';
                $x += $n;
            } else {
                $x++;
            }
        }

        return '<svg class="barras" viewBox="0 0 '.$ancho.' '.$alto.'" preserveAspectRatio="none" role="img" aria-label="Código de barras '.e($texto).'" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">'.$rects.'</g></svg>';
    }
}

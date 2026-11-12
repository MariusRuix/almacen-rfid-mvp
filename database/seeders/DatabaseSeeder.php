<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Destino;
use App\Models\Pedido;
use App\Models\Pedimento;
use App\Models\Producto;
use App\Models\Recepcion;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\Inventario;
use App\Support\Contenedor;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios para la demo. Ninguna empresa, pedimento ni contenedor es real;
 * los formatos sí siguen la forma real (pedimento de 15 dígitos, contenedor ISO 6346).
 */
class DatabaseSeeder extends Seeder
{
    public function run(Inventario $inventario): void
    {
        $responsable = User::create(['name' => 'Laura Méndez', 'email' => 'responsable@demo.test', 'password' => 'demo2026', 'rol' => 'responsable']);
        User::create(['name' => 'Jorge Ríos', 'email' => 'operador@demo.test', 'password' => 'demo2026', 'rol' => 'operador']);

        // Catálogo (mercancía con RFID de origen)
        $productos = collect([
            ['PAN-DEN-3032', 'R-101', 'Pantalón denim recto 30x32', '7501000000101', 'cinturón', 12],
            ['CAM-OXF-M', 'R-102', 'Camisa oxford manga larga M', '7501000000102', null, 24],
            ['CHA-ACO-L', 'R-103', 'Chamarra acolchada L', '7501000000103', null, 8],
            ['VES-LIN-S', 'R-104', 'Vestido de lino S', '7501000000104', 'cinturón de tela', 12],
            ['SUD-CAP-M', 'R-105', 'Sudadera con capucha M', '7501000000105', null, 12],
            ['BOL-PIE-01', 'R-106', 'Bolsa de piel mediana', '7501000000106', 'correa', 6],
            ['TEN-URB-27', 'R-107', 'Tenis urbano 27', '7501000000107', null, 10],
            ['PLA-BAS-M', 'R-108', 'Playera básica algodón M', '7501000000108', null, 24],
        ])->mapWithKeys(function ($p, $i) {
            [$sku, $ref, $desc, $upc, $acc, $ppc] = $p;

            return [$sku => Producto::create([
                'sku' => $sku, 'referencia_interna' => $ref, 'descripcion' => $desc, 'upc' => $upc,
                'epc_prefijo' => strtoupper(sprintf('3034%08X', 0x1A2B00 + $i * 17)),
                'accesorio' => $acc, 'piezas_por_caja' => $ppc,
            ])];
        });

        // Pedimentos: picos de enero, mayo y agosto + el que llega ahora
        $pedimentos = collect([
            ['26 16 3156 6000412', '2026-01-21', 'TXAU', 104772, 'Hilados del Pacífico Ltd. (ficticio)'],
            ['26 16 3156 6002187', '2026-05-12', 'TXAU', 231904, 'Hilados del Pacífico Ltd. (ficticio)'],
            ['26 16 3156 6004530', '2026-08-18', 'MRSU', 558213, 'Calzado Oriente Co. (ficticio)'],
            ['26 16 3156 6006841', '2026-11-09', 'TXAU', 402615, 'Hilados del Pacífico Ltd. (ficticio)'],
        ])->map(fn ($p) => Pedimento::create([
            'numero' => $p[0], 'clave' => 'A1', 'aduana' => 'Manzanillo, Col.', 'fecha_pago' => $p[1],
            'contenedor' => Contenedor::numero($p[2], $p[3]), 'proveedor' => $p[4],
        ]));
        [$pEne, $pMay, $pAgo, $pNov] = $pedimentos->all();

        // Ubicaciones: racks A y B (pasillo-módulo-nivel), adicionales y zonas operativas
        foreach (['A', 'B'] as $pasillo) {
            foreach (range(1, 4) as $modulo) {
                foreach (range(1, 3) as $nivel) {
                    Ubicacion::create(['codigo' => sprintf('%s-%02d-%d', $pasillo, $modulo, $nivel), 'zona' => 'rack', 'capacidad' => 600]);
                }
            }
        }
        $temporada = Area::create(['nombre' => 'Temporada', 'descripcion' => 'Ubicaciones adicionales que se habilitan en los picos de enero, mayo y agosto.', 'orden' => 2]);
        Ubicacion::create(['codigo' => 'AD-01', 'zona' => 'adicional', 'capacidad' => 900, 'area_id' => $temporada->id]);
        Ubicacion::create(['codigo' => 'AD-02', 'zona' => 'adicional', 'capacidad' => 900, 'activa' => false, 'area_id' => $temporada->id]);
        Ubicacion::create(['codigo' => 'REC-01', 'zona' => 'recepcion', 'capacidad' => 5000]);
        Ubicacion::create(['codigo' => 'PREP-01', 'zona' => 'preparacion', 'capacidad' => 5000]);
        Ubicacion::create(['codigo' => 'INC-01', 'zona' => 'incidencia', 'capacidad' => 1000]);
        $u = Ubicacion::pluck('id', 'codigo');

        // Existencias históricas: el mismo SKU en varios pedimentos y ubicaciones
        $saldoInicial = [
            // sku, pedimento, ubicación, piezas, fecha
            ['CAM-OXF-M', $pEne, 'A-01-1', 48, '2026-01-24'],
            ['CAM-OXF-M', $pMay, 'A-01-2', 240, '2026-05-15'],
            ['PLA-BAS-M', $pEne, 'A-02-1', 120, '2026-01-24'],
            ['PLA-BAS-M', $pAgo, 'A-02-2', 360, '2026-08-21'],
            ['CHA-ACO-L', $pMay, 'A-03-1', 64, '2026-05-15'],
            ['CHA-ACO-L', $pAgo, 'AD-01', 96, '2026-08-21'],
            ['PAN-DEN-3032', $pMay, 'B-01-1', 180, '2026-05-15'],
            ['VES-LIN-S', $pMay, 'B-01-2', 72, '2026-05-15'],
            ['SUD-CAP-M', $pAgo, 'B-02-1', 144, '2026-08-21'],
            ['BOL-PIE-01', $pAgo, 'B-03-1', 42, '2026-08-21'],
            ['TEN-URB-27', $pAgo, 'B-04-1', 90, '2026-08-21'],
            ['TEN-URB-27', $pEne, 'B-04-2', 20, '2026-01-24'],
        ];
        foreach ($saldoInicial as [$sku, $ped, $ubic, $piezas, $fecha]) {
            $inventario->mover('recepcion', $productos[$sku]->id, $ped->id, null,
                ['ubicacion_id' => $u[$ubic], 'estado' => 'disponible'], $piezas, null, 'Saldo inicial',
                'Carga de inventario desde Excel', $fecha.' 10:00:00', $responsable->id);
        }

        // Recepción esperada del contenedor que acaba de llegar (pedimento de noviembre)
        $recepcion = Recepcion::create([
            'folio' => 'REC-0001', 'pedimento_id' => $pNov->id, 'origen' => 'Excel del propietario',
            'fecha_esperada' => '2026-11-12',
        ]);
        foreach ([['PAN-DEN-3032', 10], ['CAM-OXF-M', 5], ['BOL-PIE-01', 8], ['SUD-CAP-M', 6], ['PLA-BAS-M', 10]] as [$sku, $cajas]) {
            $p = $productos[$sku];
            $recepcion->lineas()->create([
                'producto_id' => $p->id, 'cajas_esperadas' => $cajas, 'piezas_por_caja' => $p->piezas_por_caja,
                'piezas_esperadas' => $cajas * $p->piezas_por_caja,
            ]);
        }

        // Destinos
        $destinos = collect([
            ['T-014', 'Tienda Satélite', 'tienda', true, false],
            ['T-022', 'Tienda Coyoacán', 'tienda', true, false],
            ['T-031', 'Tienda Monterrey Valle', 'tienda', true, true],
            ['ALM-EC', 'Almacén de comercio electrónico', 'almacen', false, false],
        ])->mapWithKeys(fn ($d) => [$d[0] => Destino::create([
            'codigo' => $d[0], 'nombre' => $d[1], 'tipo' => $d[2],
            'requiere_etiqueta_comercial' => $d[3], 'requiere_sensor' => $d[4],
        ])]);

        // Pedido del propietario para varios destinos
        $pedido = Pedido::create(['folio' => 'PED-0001', 'propietario' => 'Textiles Aurora (ficticio)', 'fecha_compromiso' => '2026-11-13']);
        $reparto = [
            'T-014' => ['CAM-OXF-M' => 24, 'PLA-BAS-M' => 48, 'CHA-ACO-L' => 8],
            'T-022' => ['CAM-OXF-M' => 36, 'PLA-BAS-M' => 48, 'PAN-DEN-3032' => 24],
            'T-031' => ['CAM-OXF-M' => 12, 'CHA-ACO-L' => 16, 'PAN-DEN-3032' => 12],
            'ALM-EC' => ['PLA-BAS-M' => 96, 'TEN-URB-27' => 30, 'SUD-CAP-M' => 24],
        ];
        foreach ($reparto as $codigo => $lineas) {
            $pd = $pedido->destinos()->create(['destino_id' => $destinos[$codigo]->id]);
            foreach ($lineas as $sku => $cantidad) {
                $pd->lineas()->create(['pedido_id' => $pedido->id, 'producto_id' => $productos[$sku]->id, 'cantidad' => $cantidad]);
            }
        }

        // Un segundo pedido pequeño, para mostrar la lista
        $pedido2 = Pedido::create(['folio' => 'PED-0002', 'propietario' => 'Textiles Aurora (ficticio)', 'fecha_compromiso' => '2026-11-16']);
        $pd = $pedido2->destinos()->create(['destino_id' => $destinos['T-031']->id]);
        $pd->lineas()->create(['pedido_id' => $pedido2->id, 'producto_id' => $productos['VES-LIN-S']->id, 'cantidad' => 24]);
        $pd->lineas()->create(['pedido_id' => $pedido2->id, 'producto_id' => $productos['BOL-PIE-01']->id, 'cantidad' => 6]);
    }
}

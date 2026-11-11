<?php

/*
| Escenarios de la demo. Las diferencias aparecen en la primera lectura RFID de cada contexto;
| después de "corregí y vuelvo a leer" la lectura es exacta.
| Contexto: folio de recepción, o "FOLIO-PEDIDO:CODIGO-DESTINO" en la revisión de un pedido.
*/
return [
    'escenarios_rfid' => [
        'REC-0001' => ['PAN-DEN-3032' => -6],
        'PED-0001:T-014' => ['CAM-OXF-M' => 1],
        'PED-0001:T-022' => ['CAM-OXF-M' => -1],
    ],

    // Regla de surtido de la demo. Es una pregunta abierta para el cliente.
    'regla_surtido' => 'Primero el pedimento con fecha de pago más antigua (PEPS); dentro del pedimento, la ubicación con menos piezas.',
];

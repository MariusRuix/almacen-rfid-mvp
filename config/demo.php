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

    // Criterio provisional para proponer de qué pedimento surtir. No viene del cliente: su regla es una pregunta abierta.
    'regla_surtido' => 'Provisional, solo para la demo: se propone primero el pedimento pagado antes y, dentro de él, la ubicación con menos piezas. La regla real está por confirmar con el cliente.',
];

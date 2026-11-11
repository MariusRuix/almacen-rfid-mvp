<?php

namespace App\Services\Rfid;

interface LectorRfid
{
    /**
     * Lee las etiquetas presentes.
     *
     * @param  array<int,int>  $esperado  producto_id => piezas que deberían estar
     * @param  string  $contexto  identificador de la lectura (folio, folio:destino…)
     * @return list<string> EPCs leídos (hex de 24 caracteres)
     */
    public function leer(array $esperado, string $contexto): array;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destino extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['requiere_etiqueta_comercial' => 'boolean', 'requiere_sensor' => 'boolean'];
    }
}

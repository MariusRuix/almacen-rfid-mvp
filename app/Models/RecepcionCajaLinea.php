<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecepcionCajaLinea extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['piezas_leidas' => 'integer', 'sin_etiqueta' => 'integer', 'sin_accesorio' => 'integer', 'danadas' => 'integer'];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(RecepcionCaja::class, 'recepcion_caja_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}

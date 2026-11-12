<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecepcionLinea extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ubicada_at' => 'datetime',
            'cajas_esperadas' => 'integer',
            'piezas_por_caja' => 'integer',
            'piezas_esperadas' => 'integer',
            'piezas_leidas' => 'integer',
            'piezas_confirmadas' => 'integer',
            'piezas_sin_accesorio' => 'integer',
            'piezas_danadas' => 'integer',
            'piezas_sin_etiqueta' => 'integer',
        ];
    }

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(Recepcion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function diferencia(): ?int
    {
        return $this->piezas_confirmadas === null ? null : $this->piezas_confirmadas - $this->piezas_esperadas;
    }
}

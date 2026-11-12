<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recepcion extends Model
{
    protected $table = 'recepciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha_esperada' => 'date', 'cerrada_at' => 'datetime', 'conteo_terminado_at' => 'datetime'];
    }

    // Estados: esperada → en_conteo → contada (el auxiliar terminó; falta que la responsable cierre) → cerrada.

    public function pedimento(): BelongsTo
    {
        return $this->belongsTo(Pedimento::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(RecepcionLinea::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(RecepcionCaja::class)->orderBy('numero');
    }

    public function terminoConteo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'termino_conteo_id');
    }

    public function cerro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerro_id');
    }
}

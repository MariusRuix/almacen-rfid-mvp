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
        return ['fecha_esperada' => 'date', 'cerrada_at' => 'datetime'];
    }

    public function pedimento(): BelongsTo
    {
        return $this->belongsTo(Pedimento::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(RecepcionLinea::class);
    }

    public function cerro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerro_id');
    }
}

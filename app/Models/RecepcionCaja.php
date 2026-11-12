<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecepcionCaja extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['numero' => 'integer'];
    }

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(Recepcion::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(RecepcionCajaLinea::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

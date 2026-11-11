<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Registro inmutable: se crea, nunca se edita ni se borra. */
class Movimiento extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos no se modifican.'));
        static::deleting(fn () => throw new LogicException('Los movimientos no se borran.'));
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function pedimento(): BelongsTo
    {
        return $this->belongsTo(Pedimento::class);
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

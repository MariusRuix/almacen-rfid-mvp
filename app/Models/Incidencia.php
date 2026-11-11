<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incidencia extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['resuelta_at' => 'datetime', 'cantidad' => 'integer'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function pedimento(): BelongsTo
    {
        return $this->belongsTo(Pedimento::class);
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Destino::class);
    }

    public function reporto(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporto_id');
    }

    public function resolvio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolvio_id');
    }

    public static function siguienteFolio(): string
    {
        return sprintf('INC-%04d', (static::max('id') ?? 0) + 1);
    }
}

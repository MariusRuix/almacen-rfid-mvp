<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Existencia extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['cantidad' => 'integer'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function pedimento(): BelongsTo
    {
        return $this->belongsTo(Pedimento::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }
}

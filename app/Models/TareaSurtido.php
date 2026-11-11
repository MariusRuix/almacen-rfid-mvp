<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaSurtido extends Model
{
    protected $table = 'tareas_surtido';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tomada_at' => 'datetime', 'cantidad' => 'integer', 'cantidad_tomada' => 'integer'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
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

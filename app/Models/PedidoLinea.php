<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoLinea extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'cantidad_separada' => 'integer', 'cantidad_leida' => 'integer'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function pedidoDestino(): BelongsTo
    {
        return $this->belongsTo(PedidoDestino::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}

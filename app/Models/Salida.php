<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salida extends Model
{
    protected $guarded = [];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function autorizo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizo_id');
    }
}

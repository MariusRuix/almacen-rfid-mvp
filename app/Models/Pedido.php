<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pedido extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha_compromiso' => 'date'];
    }

    public function destinos(): HasMany
    {
        return $this->hasMany(PedidoDestino::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class);
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(TareaSurtido::class)->orderBy('orden');
    }

    public function salida(): HasOne
    {
        return $this->hasOne(Salida::class);
    }
}

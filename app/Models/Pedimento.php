<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedimento extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fecha_pago' => 'date'];
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(Existencia::class);
    }
}

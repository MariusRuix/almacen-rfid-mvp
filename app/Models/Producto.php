<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $guarded = [];

    public function existencias(): HasMany
    {
        return $this->hasMany(Existencia::class);
    }
}

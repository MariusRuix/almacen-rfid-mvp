<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['activa' => 'boolean', 'orden' => 'integer'];
    }

    public function ubicaciones(): HasMany
    {
        return $this->hasMany(Ubicacion::class);
    }

    public static function principal(): ?self
    {
        return static::orderBy('orden')->orderBy('id')->first();
    }
}

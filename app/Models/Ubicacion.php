<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    protected $table = 'ubicaciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(Existencia::class);
    }

    public static function zona(string $zona): self
    {
        return static::where('zona', $zona)->orderBy('id')->firstOrFail();
    }
}

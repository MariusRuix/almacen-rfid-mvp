<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    protected $table = 'ubicaciones';

    /** Zonas donde se guarda mercancía (se ubica, se surte, se reubica). */
    public const ALMACENAJE = ['rack', 'estanteria', 'piso', 'adicional'];

    /** Zonas del flujo: el sistema necesita al menos una de cada una. */
    public const OPERATIVAS = ['recepcion', 'preparacion', 'incidencia'];

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

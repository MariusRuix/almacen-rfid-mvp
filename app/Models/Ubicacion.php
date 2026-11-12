<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected static function booted(): void
    {
        // Sin área elegida, va al área principal.
        static::creating(function (self $u) {
            $u->area_id ??= Area::principal()?->id;
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** Puede recibir mercancía: ubicación activa dentro de un área activa. */
    public function scopeDisponibles(Builder $q): Builder
    {
        return $q->where('activa', true)->where(fn ($w) => $w->whereNull('area_id')->orWhereHas('area', fn ($a) => $a->where('activa', true)));
    }

    public function disponible(): bool
    {
        return $this->activa && ($this->area?->activa ?? true);
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

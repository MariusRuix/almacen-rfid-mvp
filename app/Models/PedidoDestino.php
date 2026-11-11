<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoDestino extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'etiqueta_importacion' => 'boolean',
            'etiqueta_comercial' => 'boolean',
            'sensor' => 'boolean',
            'verificado_at' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Destino::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class);
    }

    /** Actividades de preparación que aplican a este destino. */
    public function actividades(): array
    {
        $a = ['etiqueta_importacion' => 'Etiqueta de importación'];
        if ($this->destino->requiere_etiqueta_comercial) {
            $a['etiqueta_comercial'] = 'Etiqueta comercial';
        }
        if ($this->destino->requiere_sensor) {
            $a['sensor'] = 'Sensor';
        }

        return $a;
    }

    public function actividadesCompletas(): bool
    {
        foreach (array_keys($this->actividades()) as $campo) {
            if (! $this->{$campo}) {
                return false;
            }
        }

        return true;
    }
}

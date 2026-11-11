<?php

namespace App\Providers;

use App\Services\Rfid\LectorRfid;
use App\Services\Rfid\LectorSimulado;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Para un lector real, implementar LectorRfid y cambiar este binding según config('rfid.lector').
        $this->app->bind(LectorRfid::class, LectorSimulado::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'recepcion' => \App\Models\Recepcion::class,
            'pedido' => \App\Models\Pedido::class,
            'salida' => \App\Models\Salida::class,
            'incidencia' => \App\Models\Incidencia::class,
        ]);
    }
}

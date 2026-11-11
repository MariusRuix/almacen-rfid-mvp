<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('demo:reiniciar', function () {
    $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
    $this->info('Demo reiniciada con datos ficticios.');
})->purpose('Borra todo y vuelve a cargar los datos ficticios de la demo');

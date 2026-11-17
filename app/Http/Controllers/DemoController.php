<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DemoController extends Controller
{
    /** Borra todo y vuelve a cargar los datos ficticios. Útil entre ensayos de la presentación. */
    public function reiniciar(Request $request)
    {
        $email = $request->user()->email;
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        Storage::disk('local')->deleteDirectory('evidencias');
        $request->session()->forget('rfid_releer');

        $usuario = \App\Models\User::where('email', $email)->first();
        if ($usuario) {
            Auth::login($usuario, true);
        }

        return redirect('/')->with('ok', 'Demo reiniciada con los datos ficticios de origen.');
    }
}

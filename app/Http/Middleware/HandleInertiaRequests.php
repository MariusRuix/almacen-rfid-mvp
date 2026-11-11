<?php

namespace App\Http\Middleware;

use App\Models\Incidencia;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => ['nombre' => config('app.name')],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'rol' => $user->rol,
                ] : null,
            ],
            'incidenciasAbiertas' => fn () => $user ? Incidencia::where('estado', 'abierta')->count() : 0,
            'flash' => [
                'ok' => fn () => $request->session()->get('ok'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

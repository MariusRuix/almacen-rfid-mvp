<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'usuariosDemo' => [
                ['rol' => 'Responsable operativa', 'email' => 'responsable@demo.test'],
                ['rol' => 'Operador de almacén', 'email' => 'operador@demo.test'],
            ],
            'passwordDemo' => 'demo2026',
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [], ['email' => 'correo', 'password' => 'contraseña']);

        if (! Auth::attempt($datos, true)) {
            return back()->withErrors(['email' => 'El correo o la contraseña no coinciden.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(Auth::user()->esResponsable() ? '/' : '/operador');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}

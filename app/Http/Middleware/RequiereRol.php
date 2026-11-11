<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiereRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->rol, $roles, true)) {
            return back()->with('error', 'Esta acción la realiza la responsable operativa.');
        }

        return $next($request);
    }
}

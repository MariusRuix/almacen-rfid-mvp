<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EvidenciaController extends Controller
{
    public function store(Request $request)
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(Evidencia::TIPOS))],
            'id' => ['required', 'integer'],
            'momento' => ['required', 'string'],
            'nota' => ['nullable', 'string', 'max:200'],
            ...Evidencia::reglas(),
        ], Evidencia::MENSAJES);

        [$modelo, $momentos] = Evidencia::TIPOS[$datos['tipo']];
        abort_unless(in_array($datos['momento'], $momentos, true), 422);
        $modelo::findOrFail($datos['id']);

        $n = Evidencia::guardar($datos['tipo'], (int) $datos['id'], $datos['momento'], $request->file('archivos'), $request->user()->id, $datos['nota'] ?? null);

        return back()->with('ok', $n === 1 ? 'Evidencia guardada.' : "{$n} evidencias guardadas.");
    }

    /** Se sirve solo con sesión iniciada; no queda en una carpeta pública. */
    public function show(Evidencia $evidencia)
    {
        abort_unless(Storage::disk('local')->exists($evidencia->archivo), 404);

        return Storage::disk('local')->response($evidencia->archivo, $evidencia->nombre, [
            'Content-Type' => $evidencia->mime,
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }

    public function destroy(Request $request, Evidencia $evidencia)
    {
        $usuario = $request->user();
        abort_unless($usuario->rol === 'responsable' || $evidencia->user_id === $usuario->id, 403);
        Storage::disk('local')->delete($evidencia->archivo);
        $evidencia->delete();

        return back()->with('ok', 'Evidencia eliminada.');
    }
}

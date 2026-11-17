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
            'archivos' => ['required', 'array', 'min:1', 'max:8'],
            'archivos.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:12288'],
            'nota' => ['nullable', 'string', 'max:200'],
        ], [
            'archivos.required' => 'Elige al menos una foto o documento.',
            'archivos.max' => 'Sube hasta 8 archivos a la vez.',
            'archivos.*.mimes' => 'Solo se aceptan fotos (JPG, PNG, WEBP, HEIC) o PDF.',
            'archivos.*.max' => 'Cada archivo puede pesar hasta 12 MB.',
            'archivos.*.uploaded' => 'El archivo no se pudo subir; revisa que no pase del límite del servidor.',
        ]);

        [$modelo, $momentos] = Evidencia::TIPOS[$datos['tipo']];
        abort_unless(in_array($datos['momento'], $momentos, true), 422);
        $modelo::findOrFail($datos['id']);

        foreach ($request->file('archivos') as $archivo) {
            Evidencia::create([
                'tipo' => $datos['tipo'],
                'referencia_id' => $datos['id'],
                'momento' => $datos['momento'],
                'archivo' => $archivo->store("evidencias/{$datos['tipo']}/{$datos['id']}", 'local'),
                'nombre' => $archivo->getClientOriginalName(),
                'mime' => $archivo->getMimeType() ?? 'application/octet-stream',
                'tamano' => $archivo->getSize(),
                'nota' => $datos['nota'] ?? null,
                'user_id' => $request->user()->id,
            ]);
        }
        $n = count($request->file('archivos'));

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

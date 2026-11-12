<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Existencia;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Áreas ligeras: agrupan ubicaciones y se activan o desactivan completas (p. ej. en temporada). */
class AreaController extends Controller
{
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:60', 'unique:areas,nombre'],
            'descripcion' => ['nullable', 'string', 'max:160'],
        ], $this->mensajes());
        $area = Area::create($datos + ['orden' => (int) Area::max('orden') + 1]);

        return back()->with('ok', 'Área "'.$area->nombre.'" creada. Agrégale pasillos o posiciones.');
    }

    public function update(Request $request, Area $area)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('areas', 'nombre')->ignore($area->id)],
            'descripcion' => ['nullable', 'string', 'max:160'],
            'activa' => ['required', 'boolean'],
        ], $this->mensajes());

        if (! $datos['activa'] && $area->activa) {
            $ids = $area->ubicaciones()->pluck('id');
            if (Ubicacion::whereIn('id', $ids)->whereIn('zona', Ubicacion::OPERATIVAS)->exists()) {
                return back()->with('error', '"'.$area->nombre.'" tiene zonas de recepción, preparación o incidencias: no se puede desactivar.');
            }
            $piezas = (int) Existencia::whereIn('ubicacion_id', $ids)->sum('cantidad');
            if ($piezas > 0) {
                return back()->with('error', '"'.$area->nombre.'" tiene '.number_format($piezas).' piezas: reubícalas antes de desactivarla.');
            }
        }

        $area->update($datos);

        return back()->with('ok', 'Área "'.$area->nombre.'" '.($datos['activa'] ? 'actualizada.' : 'desactivada: sus ubicaciones ya no reciben mercancía.'));
    }

    public function destroy(Area $area)
    {
        if ($area->ubicaciones()->exists()) {
            return back()->with('error', '"'.$area->nombre.'" todavía tiene ubicaciones. Muévelas a otra área o desactívala.');
        }
        if (Area::count() === 1) {
            return back()->with('error', 'Debe existir al menos un área.');
        }
        $area->delete();

        return back()->with('ok', 'Área "'.$area->nombre.'" borrada.');
    }

    private function mensajes(): array
    {
        return [
            'nombre.required' => 'Ponle nombre al área.',
            'nombre.unique' => 'Ya existe un área con ese nombre.',
        ];
    }
}

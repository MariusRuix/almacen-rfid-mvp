<?php

namespace App\Http\Controllers;

use App\Models\Destino;
use App\Models\PedidoDestino;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Catálogo de destinos: tiendas y almacenes a los que se reparte. */
class DestinoController extends Controller
{
    public function index(): Response
    {
        $uso = PedidoDestino::join('pedidos', 'pedidos.id', '=', 'pedido_destinos.pedido_id')
            ->selectRaw("destino_id, count(*) as pedidos, sum(case when pedidos.estado != 'despachado' then 1 else 0 end) as abiertos")
            ->groupBy('destino_id')->get()->keyBy('destino_id');

        return Inertia::render('Destinos/Index', [
            'destinos' => Destino::orderBy('codigo')->get()->map(fn (Destino $d) => [
                'id' => $d->id,
                'codigo' => $d->codigo,
                'nombre' => $d->nombre,
                'tipo' => $d->tipo,
                'requiere_etiqueta_comercial' => $d->requiere_etiqueta_comercial,
                'requiere_sensor' => $d->requiere_sensor,
                'pedidos' => (int) ($uso[$d->id]->pedidos ?? 0),
                'abiertos' => (int) ($uso[$d->id]->abiertos ?? 0),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $destino = Destino::create($this->validar($request));

        return back()->with('ok', 'Destino '.$destino->codigo.' creado.');
    }

    public function update(Request $request, Destino $destino)
    {
        $destino->update($this->validar($request, $destino));

        return back()->with('ok', 'Destino '.$destino->codigo.' actualizado.');
    }

    public function destroy(Destino $destino)
    {
        if (PedidoDestino::where('destino_id', $destino->id)->exists()) {
            return back()->with('error', 'No se puede borrar '.$destino->codigo.': ya tiene pedidos. El historial debe conservarlo.');
        }
        $destino->delete();

        return back()->with('ok', 'Destino '.$destino->codigo.' borrado.');
    }

    private function validar(Request $request, ?Destino $destino = null): array
    {
        $request->merge(['codigo' => strtoupper(trim((string) $request->input('codigo')))]);

        return $request->validate([
            'codigo' => ['required', 'string', 'max:12', 'regex:/^[A-Z0-9-]+$/', Rule::unique('destinos', 'codigo')->ignore($destino?->id)],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(['tienda', 'almacen'])],
            'requiere_etiqueta_comercial' => ['boolean'],
            'requiere_sensor' => ['boolean'],
        ], [
            'codigo.required' => 'Captura el código del destino.',
            'codigo.unique' => 'Ya existe un destino con ese código.',
            'codigo.regex' => 'Solo letras, números y guiones, por ejemplo T-045.',
            'codigo.max' => 'Máximo 12 caracteres.',
            'nombre.required' => 'Captura el nombre del destino.',
            'tipo.in' => 'Elige tienda o almacén.',
        ]);
    }
}

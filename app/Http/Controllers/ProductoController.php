<?php

namespace App\Http\Controllers;

use App\Models\Existencia;
use App\Models\Movimiento;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\RecepcionLinea;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Catálogo de productos (SKU). El prefijo RFID se asigna solo. */
class ProductoController extends Controller
{
    public function index(): Response
    {
        $piezas = Existencia::selectRaw('producto_id, sum(cantidad) as piezas')->groupBy('producto_id')->pluck('piezas', 'producto_id');
        $conMovimientos = Movimiento::distinct()->pluck('producto_id')->flip();
        $enDocumentos = RecepcionLinea::distinct()->pluck('producto_id')
            ->merge(PedidoLinea::distinct()->pluck('producto_id'))->unique()->flip();

        return Inertia::render('Productos/Index', [
            'productos' => Producto::orderBy('sku')->get()->map(fn (Producto $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'referencia' => $p->referencia_interna,
                'descripcion' => $p->descripcion,
                'upc' => $p->upc,
                'epc_prefijo' => $p->epc_prefijo,
                'piezas_por_caja' => $p->piezas_por_caja,
                'accesorio' => $p->accesorio,
                'existencia' => (int) ($piezas[$p->id] ?? 0),
                'en_uso' => isset($conMovimientos[$p->id]) || isset($enDocumentos[$p->id]),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        do {
            $prefijo = '3034'.strtoupper(bin2hex(random_bytes(4)));
        } while (Producto::where('epc_prefijo', $prefijo)->exists());

        $producto = Producto::create($datos + ['epc_prefijo' => $prefijo]);

        return back()->with('ok', 'Producto '.$producto->sku.' creado.');
    }

    public function update(Request $request, Producto $producto)
    {
        $producto->update($this->validar($request, $producto));

        return back()->with('ok', 'Producto '.$producto->sku.' actualizado.');
    }

    public function destroy(Producto $producto)
    {
        $enUso = Movimiento::where('producto_id', $producto->id)->exists()
            || RecepcionLinea::where('producto_id', $producto->id)->exists()
            || PedidoLinea::where('producto_id', $producto->id)->exists();
        if ($enUso) {
            return back()->with('error', 'No se puede borrar '.$producto->sku.': ya aparece en recepciones, pedidos o en el historial.');
        }
        $producto->delete();

        return back()->with('ok', 'Producto '.$producto->sku.' borrado.');
    }

    private function validar(Request $request, ?Producto $producto = null): array
    {
        $request->merge([
            'sku' => strtoupper(trim((string) $request->input('sku'))),
            'referencia_interna' => trim((string) $request->input('referencia_interna')) ?: null,
            'upc' => preg_replace('/\D/', '', (string) $request->input('upc')) ?: null,
            'accesorio' => trim((string) $request->input('accesorio')) ?: null,
        ]);

        return $request->validate([
            'sku' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', Rule::unique('productos', 'sku')->ignore($producto?->id)],
            'referencia_interna' => ['nullable', 'string', 'max:20'],
            'descripcion' => ['required', 'string', 'max:160'],
            'upc' => ['nullable', 'digits_between:8,14'],
            'piezas_por_caja' => ['required', 'integer', 'min:1', 'max:10000'],
            'accesorio' => ['nullable', 'string', 'max:60'],
        ], [
            'sku.required' => 'Captura el SKU.',
            'sku.unique' => 'Ya existe un producto con ese SKU.',
            'sku.regex' => 'Solo letras, números y guiones, por ejemplo CAM-OXF-L.',
            'descripcion.required' => 'Captura la descripción.',
            'upc.digits_between' => 'El código de barras lleva de 8 a 14 dígitos.',
            'piezas_por_caja.required' => 'Indica las piezas por caja.',
            'piezas_por_caja.min' => 'Al menos 1 pieza por caja.',
            'piezas_por_caja.integer' => 'Solo números enteros.',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Recepcion;
use App\Models\RecepcionCaja;
use App\Services\ConteoPorCajas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Recepción en piso: el auxiliar lee caja por caja y marca lo que el RFID no ve. */
class OperadorRecepcionController extends Controller
{
    public function show(Recepcion $recepcion): Response
    {
        $recepcion->load(['pedimento', 'lineas.producto', 'cajas.lineas.producto', 'cajas.usuario', 'terminoConteo']);

        return Inertia::render('Operador/Recepcion', [
            'recepcion' => [
                'id' => $recepcion->id,
                'folio' => $recepcion->folio,
                'estado' => $recepcion->estado,
                'pedimento' => Presentar::pedimento($recepcion->pedimento),
                'conteo_terminado_at' => $recepcion->conteo_terminado_at?->toDateTimeString(),
                'termino' => $recepcion->terminoConteo?->name,
                'cajas_esperadas' => (int) $recepcion->lineas->sum('cajas_esperadas'),
                'piezas_esperadas' => (int) $recepcion->lineas->sum('piezas_esperadas'),
                'lineas' => $recepcion->lineas->map(fn ($l) => [
                    'id' => $l->id,
                    'producto' => Presentar::producto($l->producto),
                    'cajas_esperadas' => $l->cajas_esperadas,
                    'piezas_por_caja' => $l->piezas_por_caja,
                    'esperadas' => $l->piezas_esperadas,
                    'leidas' => (int) $l->piezas_leidas,
                    'confirmadas' => (int) $l->piezas_confirmadas,
                    'cajas_leidas' => $recepcion->cajas->where('estado', 'leida')
                        ->filter(fn ($c) => $c->lineas->contains('producto_id', $l->producto_id))->count(),
                ]),
                'cajas' => $recepcion->cajas->sortByDesc('numero')->values()->map(fn (RecepcionCaja $c) => [
                    'id' => $c->id,
                    'numero' => $c->numero,
                    'estado' => $c->estado,
                    'origen' => $c->origen,
                    'usuario' => $c->usuario?->name,
                    'hora' => $c->created_at->toDateTimeString(),
                    'lineas' => $c->lineas->map(fn ($cl) => [
                        'id' => $cl->id,
                        'producto' => Presentar::producto($cl->producto),
                        'leidas' => $cl->piezas_leidas,
                        'sin_etiqueta' => $cl->sin_etiqueta,
                        'sin_accesorio' => $cl->sin_accesorio,
                        'danadas' => $cl->danadas,
                        'esperadas_por_caja' => optional($recepcion->lineas->firstWhere('producto_id', $cl->producto_id))->piezas_por_caja,
                    ]),
                ]),
            ],
            // Para el escáner de código de barras: se busca por código o por SKU.
            'catalogo' => Producto::orderBy('sku')->get(['id', 'sku', 'upc', 'descripcion', 'piezas_por_caja', 'accesorio']),
        ]);
    }

    /** Caja contada sin lector RFID: a mano o escaneando códigos de barras. */
    public function registrarManual(Request $request, Recepcion $recepcion, ConteoPorCajas $conteo)
    {
        abort_if($recepcion->estado === 'cerrada', 422);
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'piezas' => ['required', 'integer', 'min:1', 'max:10000'],
            'origen' => ['required', 'in:manual,codigo'],
        ], [
            'producto_id.*' => 'Elige o escanea un SKU del catálogo.',
            'piezas.*' => 'Indica cuántas piezas trae la caja (1 o más).',
        ]);
        $caja = $conteo->registrarManual($recepcion, $datos['producto_id'], $datos['piezas'], $datos['origen'], $request->user()->id);
        $sku = Producto::find($datos['producto_id'])->sku;

        return back()->with('ok', 'Caja '.$caja->numero.' registrada '.($datos['origen'] === 'codigo' ? 'por código de barras' : 'a mano').': '.$datos['piezas'].' × '.$sku.'.');
    }

    public function leerCaja(Request $request, Recepcion $recepcion, ConteoPorCajas $conteo)
    {
        abort_if($recepcion->estado === 'cerrada', 422);
        $caja = $conteo->leerCaja($recepcion, $request->user()->id);
        if (! $caja) {
            return back()->with('error', 'Ya no quedan cajas por leer en la simulación. Termina el conteo.');
        }
        $resumen = $caja->lineas()->with('producto')->get()->map(fn ($l) => $l->piezas_leidas.' × '.$l->producto->sku)->implode(', ');

        return back()->with('ok', 'Caja '.$caja->numero.': '.$resumen.'.');
    }

    public function releer(RecepcionCaja $caja, ConteoPorCajas $conteo)
    {
        abort_if($caja->recepcion->estado === 'cerrada' || $caja->origen !== 'rfid', 422);
        $conteo->releerCaja($caja->load('lineas'));

        return back()->with('ok', 'Caja '.$caja->numero.' leída de nuevo.');
    }

    /** Lo que el RFID no ve: piezas sin etiqueta (contadas a mano), sin accesorio y dañadas. */
    public function marcar(Request $request, RecepcionCaja $caja, ConteoPorCajas $conteo)
    {
        abort_if($caja->recepcion->estado === 'cerrada', 422);
        $datos = $request->validate([
            'lineas' => ['required', 'array'],
            'lineas.*.sin_etiqueta' => ['required', 'integer', 'min:0', 'max:10000'],
            'lineas.*.sin_accesorio' => ['required', 'integer', 'min:0', 'max:10000'],
            'lineas.*.danadas' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $caja->load('lineas.producto');
        foreach ($caja->lineas as $l) {
            $m = $datos['lineas'][$l->id] ?? null;
            if (! $m) {
                continue;
            }
            if ($m['sin_accesorio'] + $m['danadas'] > $l->piezas_leidas + $m['sin_etiqueta']) {
                return back()->with('error', $l->producto->sku.': las piezas sin accesorio y dañadas no pueden ser más que las de la caja.');
            }
        }

        DB::transaction(function () use ($caja, $datos, $conteo) {
            foreach ($caja->lineas as $l) {
                if ($m = $datos['lineas'][$l->id] ?? null) {
                    $l->update(['sin_etiqueta' => $m['sin_etiqueta'], 'sin_accesorio' => $m['sin_accesorio'], 'danadas' => $m['danadas']]);
                }
            }
            $conteo->recalcular($caja->recepcion);
        });

        return back()->with('ok', 'Caja '.$caja->numero.' actualizada.');
    }

    public function anular(RecepcionCaja $caja, ConteoPorCajas $conteo)
    {
        abort_if($caja->recepcion->estado === 'cerrada', 422);
        $caja->update(['estado' => $caja->estado === 'anulada' ? 'leida' : 'anulada']);
        $conteo->recalcular($caja->recepcion);

        return back()->with('ok', $caja->estado === 'anulada'
            ? 'Caja '.$caja->numero.' anulada: ya no cuenta en el total.'
            : 'Caja '.$caja->numero.' vuelve a contar.');
    }

    /** El auxiliar termina; la responsable revisa y cierra desde su panel. */
    public function terminar(Request $request, Recepcion $recepcion)
    {
        abort_if($recepcion->estado === 'cerrada', 422);
        if (! $recepcion->cajas()->where('estado', 'leida')->exists()) {
            return back()->with('error', 'Lee al menos una caja antes de terminar.');
        }
        $recepcion->update(['conteo_terminado_at' => now(), 'termino_conteo_id' => $request->user()->id]);

        return redirect('/operador')->with('ok', 'Conteo de '.$recepcion->folio.' terminado. La responsable lo revisa y cierra.');
    }
}

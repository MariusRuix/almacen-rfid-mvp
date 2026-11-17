<?php

namespace App\Support;

use App\Models\Evidencia;
use App\Models\Incidencia;
use App\Models\Movimiento;
use App\Models\PedidoDestino;
use App\Models\RecepcionCaja;
use App\Models\Salida;
use Illuminate\Support\Collection;

/**
 * Junta las fotos de un documento para consultarlas desde el historial.
 * Un movimiento apunta a su documento (recepción, pedido, salida o incidencia); el expediente reúne
 * las fotos del documento y de lo que cuelga de él: cajas, bultos e incidencias que nacieron ahí.
 */
class Expediente
{
    /** Documento raíz de un movimiento: ['recepcion'|'pedido'|'incidencia', id] o null. */
    public static function raiz(?string $tipo, ?int $id): ?array
    {
        return match ($tipo) {
            'recepcion', 'pedido', 'incidencia' => $id ? [$tipo, $id] : null,
            'salida' => ($p = Salida::whereKey($id)->value('pedido_id')) ? ['pedido', (int) $p] : null,
            default => null,
        };
    }

    /** Las evidencias del documento y de lo que cuelga de él. */
    public static function evidencias(string $tipo, int $id): Collection
    {
        $q = Evidencia::with('usuario');
        $incidencias = fn (string $origen) => Incidencia::where('origen_type', $origen)->where('origen_id', $id)->pluck('id');

        return match ($tipo) {
            'recepcion' => $q->where(fn ($w) => $w
                ->where(fn ($x) => $x->where('tipo', 'recepcion')->where('referencia_id', $id))
                ->orWhere(fn ($x) => $x->where('tipo', 'caja')->whereIn('referencia_id', RecepcionCaja::where('recepcion_id', $id)->pluck('id')))
                ->orWhere(fn ($x) => $x->where('tipo', 'incidencia')->whereIn('referencia_id', $incidencias('recepcion'))))->orderBy('id')->get(),
            'pedido' => $q->where(fn ($w) => $w
                ->where(fn ($x) => $x->where('tipo', 'pedido')->where('referencia_id', $id))
                ->orWhere(fn ($x) => $x->where('tipo', 'destino')->whereIn('referencia_id', PedidoDestino::where('pedido_id', $id)->pluck('id')))
                ->orWhere(fn ($x) => $x->where('tipo', 'incidencia')->whereIn('referencia_id', $incidencias('pedido'))))->orderBy('id')->get(),
            'incidencia' => $q->where('tipo', 'incidencia')->where('referencia_id', $id)->orderBy('id')->get(),
            default => collect(),
        };
    }

    /** Fotos agrupadas por momento, con un título que se entiende sin abrir el documento. */
    public static function grupos(Collection $evidencias): array
    {
        $cajas = RecepcionCaja::whereIn('id', $evidencias->where('tipo', 'caja')->pluck('referencia_id'))->pluck('numero', 'id');
        $destinos = PedidoDestino::with('destino')->whereIn('id', $evidencias->where('tipo', 'destino')->pluck('referencia_id'))->get()->keyBy('id');
        $incs = Incidencia::whereIn('id', $evidencias->where('tipo', 'incidencia')->pluck('referencia_id'))->get()->keyBy('id');

        return $evidencias->groupBy(fn (Evidencia $e) => match ($e->tipo) {
            'recepcion' => 'Llegada del contenedor',
            'caja' => 'Caja C'.str_pad((string) ($cajas[$e->referencia_id] ?? '?'), 2, '0', STR_PAD_LEFT),
            'incidencia' => $incs->get($e->referencia_id)?->folio ?? 'Incidencia',
            'destino' => 'Bulto '.($destinos->get($e->referencia_id)?->destino?->codigo ?? ''),
            'pedido' => $e->momento === 'acuse' ? 'Acuse de la tienda' : 'Carga y sello',
            default => 'Otras',
        })->map(fn ($grupo, $titulo) => ['titulo' => $titulo, 'evidencias' => Evidencia::paraVista($grupo)])->values()->all();
    }

    /** Cuántas fotos tiene el documento de cada movimiento (una consulta por documento distinto). */
    public static function conteos(iterable $movimientos): array
    {
        $porDocumento = [];
        $conteos = [];
        foreach ($movimientos as $m) {
            $raiz = self::raiz($m->referencia_type, $m->referencia_id);
            if (! $raiz) {
                continue;
            }
            $clave = implode(':', $raiz);
            $porDocumento[$clave] ??= self::evidencias(...$raiz)->count();
            $conteos[$m->id] = $porDocumento[$clave];
        }

        return $conteos;
    }

    /** Pares [referencia_type => ids] de movimientos cuyo documento tiene al menos una foto. */
    public static function documentosConFotos(): array
    {
        $ev = Evidencia::select('tipo', 'referencia_id')->distinct()->get()->groupBy('tipo')->map->pluck('referencia_id');
        $de = fn (string $t) => $ev->get($t, collect());

        $incs = Incidencia::whereIn('id', $de('incidencia'))->get(['id', 'origen_type', 'origen_id']);
        $recepciones = $de('recepcion')
            ->merge(RecepcionCaja::whereIn('id', $de('caja'))->pluck('recepcion_id'))
            ->merge($incs->where('origen_type', 'recepcion')->pluck('origen_id'))->unique()->values();
        $pedidos = $de('pedido')
            ->merge(PedidoDestino::whereIn('id', $de('destino'))->pluck('pedido_id'))
            ->merge($incs->where('origen_type', 'pedido')->pluck('origen_id'))->unique()->values();

        return [
            'recepcion' => $recepciones->all(),
            'pedido' => $pedidos->all(),
            'salida' => Salida::whereIn('pedido_id', $pedidos)->pluck('id')->all(),
            'incidencia' => $incs->pluck('id')->all(),
        ];
    }

    /** Liga y folio del documento raíz, para el panel. */
    public static function documento(Movimiento $m): ?array
    {
        $raiz = self::raiz($m->referencia_type, $m->referencia_id);
        if (! $raiz) {
            return null;
        }
        [$tipo, $id] = $raiz;

        return match ($tipo) {
            'recepcion' => ['tipo' => 'Recepción', 'folio' => \App\Models\Recepcion::whereKey($id)->value('folio'), 'href' => "/recepciones/{$id}"],
            'pedido' => ['tipo' => 'Pedido', 'folio' => \App\Models\Pedido::whereKey($id)->value('folio'), 'href' => "/pedidos/{$id}"],
            'incidencia' => ['tipo' => 'Incidencia', 'folio' => Incidencia::whereKey($id)->value('folio'), 'href' => '/incidencias'],
        };
    }
}

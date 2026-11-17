<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Foto o documento ligado a un paso. Se guarda en el disco privado y solo se sirve a usuarios con sesión.
 * `tipo` + `referencia_id` dicen a qué pertenece; `momento`, en qué punto del proceso se tomó.
 */
class Evidencia extends Model
{
    protected $table = 'evidencias';

    protected $guarded = [];

    /** A qué se puede ligar una evidencia y en qué momentos. */
    public const TIPOS = [
        'recepcion' => [Recepcion::class, ['llegada']],
        'caja' => [RecepcionCaja::class, ['caja']],
        'incidencia' => [Incidencia::class, ['incidencia']],
        'destino' => [PedidoDestino::class, ['bulto']],
        'pedido' => [Pedido::class, ['carga', 'acuse']],
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function esImagen(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Evidencias de varios registros de un tipo, agrupadas por id del registro. */
    public static function de(string $tipo, iterable $ids): Collection
    {
        return static::with('usuario')->where('tipo', $tipo)->whereIn('referencia_id', collect($ids)->all())
            ->orderBy('id')->get()->groupBy('referencia_id');
    }

    public static function paraVista(?Collection $evidencias): array
    {
        return ($evidencias ?? collect())->map(fn (self $e) => [
            'id' => $e->id,
            'url' => '/evidencias/'.$e->id,
            'nombre' => $e->nombre,
            'es_imagen' => $e->esImagen(),
            'momento' => $e->momento,
            'nota' => $e->nota,
            'usuario' => $e->usuario?->name,
            'usuario_id' => $e->user_id,
            'fecha' => $e->created_at->toDateTimeString(),
        ])->values()->all();
    }
}

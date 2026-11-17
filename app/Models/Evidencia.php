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

    /**
     * Guarda los archivos subidos como evidencias de un registro. Devuelve cuántos guardó.
     *
     * @param  \Illuminate\Http\UploadedFile[]  $archivos
     */
    public static function guardar(string $tipo, int $id, string $momento, array $archivos, ?int $userId, ?string $nota = null): int
    {
        foreach ($archivos as $archivo) {
            static::create([
                'tipo' => $tipo,
                'referencia_id' => $id,
                'momento' => $momento,
                'archivo' => $archivo->store("evidencias/{$tipo}/{$id}", 'local'),
                'nombre' => $archivo->getClientOriginalName(),
                'mime' => $archivo->getMimeType() ?? 'application/octet-stream',
                'tamano' => $archivo->getSize(),
                'nota' => $nota,
                'user_id' => $userId,
            ]);
        }

        return count($archivos);
    }

    /** Reglas de validación compartidas para los archivos. */
    public static function reglas(bool $requeridos = true): array
    {
        return [
            'archivos' => [$requeridos ? 'required' : 'nullable', 'array', 'max:8'],
            'archivos.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:12288'],
        ];
    }

    public const MENSAJES = [
        'archivos.required' => 'Elige al menos una foto o documento.',
        'archivos.max' => 'Sube hasta 8 archivos a la vez.',
        'archivos.*.mimes' => 'Solo se aceptan fotos (JPG, PNG, WEBP, HEIC) o PDF.',
        'archivos.*.max' => 'Cada archivo puede pesar hasta 12 MB.',
        'archivos.*.uploaded' => 'El archivo no se pudo subir; revisa que no pase del límite del servidor.',
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

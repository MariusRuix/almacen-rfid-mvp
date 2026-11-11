<?php

namespace App\Services;

use App\Models\Existencia;
use App\Models\Movimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Único punto que cambia existencias. Cada cambio deja un movimiento inmutable,
 * así el saldo siempre se puede reconstruir desde el historial.
 */
class Inventario
{
    /**
     * @param  array{ubicacion_id:int|null, estado:string|null}|null  $desde  null = entra al almacén
     * @param  array{ubicacion_id:int|null, estado:string|null}|null  $hacia  null = sale del almacén
     */
    public function mover(
        string $tipo,
        int $productoId,
        int $pedimentoId,
        ?array $desde,
        ?array $hacia,
        int $cantidad,
        ?Model $referencia = null,
        ?string $folio = null,
        ?string $nota = null,
        ?string $fecha = null,
        ?int $userId = null,
    ): Movimiento {
        if ($cantidad <= 0) {
            throw new RuntimeException('La cantidad del movimiento debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($tipo, $productoId, $pedimentoId, $desde, $hacia, $cantidad, $referencia, $folio, $nota, $fecha, $userId) {
            if ($desde) {
                $origen = Existencia::where([
                    'producto_id' => $productoId,
                    'pedimento_id' => $pedimentoId,
                    'ubicacion_id' => $desde['ubicacion_id'],
                    'estado' => $desde['estado'],
                ])->lockForUpdate()->first();

                if (! $origen || $origen->cantidad < $cantidad) {
                    throw new RuntimeException('No hay existencia suficiente en el origen para mover '.$cantidad.' piezas.');
                }

                $origen->decrement('cantidad', $cantidad);
                if ((int) $origen->fresh()->cantidad === 0) {
                    $origen->delete();
                }
            }

            if ($hacia) {
                $destino = Existencia::firstOrCreate([
                    'producto_id' => $productoId,
                    'pedimento_id' => $pedimentoId,
                    'ubicacion_id' => $hacia['ubicacion_id'],
                    'estado' => $hacia['estado'],
                ], ['cantidad' => 0]);
                $destino->increment('cantidad', $cantidad);
            }

            $atributos = [
                'tipo' => $tipo,
                'producto_id' => $productoId,
                'pedimento_id' => $pedimentoId,
                'ubicacion_origen_id' => $desde['ubicacion_id'] ?? null,
                'estado_origen' => $desde['estado'] ?? null,
                'ubicacion_destino_id' => $hacia['ubicacion_id'] ?? null,
                'estado_destino' => $hacia['estado'] ?? null,
                'cantidad' => $cantidad,
                'referencia_type' => $referencia?->getMorphClass(),
                'referencia_id' => $referencia?->getKey(),
                'referencia_folio' => $folio,
                'user_id' => $userId ?? Auth::id(),
                'nota' => $nota,
            ];
            if ($fecha) {
                $atributos['created_at'] = $fecha;
            }

            return Movimiento::create($atributos);
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Saldo vigente. Siempre se modifica a través de App\Services\Inventario (nunca a mano).
        Schema::create('existencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained();
            $table->foreignId('pedimento_id')->constrained();
            $table->foreignId('ubicacion_id')->constrained('ubicaciones');
            $table->string('estado', 20); // disponible | en_preparacion | incidencia
            $table->integer('cantidad')->default(0);
            $table->timestamps();
            $table->unique(['producto_id', 'pedimento_id', 'ubicacion_id', 'estado'], 'existencia_unica');
        });

        // Historial inmutable: cada cambio de existencia es un movimiento.
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // recepcion | ubicacion | surtido | separacion | ajuste | incidencia | liberacion | salida
            $table->foreignId('producto_id')->constrained();
            $table->foreignId('pedimento_id')->constrained();
            $table->foreignId('ubicacion_origen_id')->nullable()->constrained('ubicaciones');
            $table->foreignId('ubicacion_destino_id')->nullable()->constrained('ubicaciones');
            $table->string('estado_origen', 20)->nullable();
            $table->string('estado_destino', 20)->nullable();
            $table->integer('cantidad');
            $table->nullableMorphs('referencia');
            $table->string('referencia_folio', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('nota')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('tipo', 30); // faltante | sobrante | accesorio_faltante | danado | destino_equivocado
            $table->nullableMorphs('origen');
            $table->string('origen_folio', 30)->nullable();
            $table->foreignId('producto_id')->constrained();
            $table->foreignId('pedimento_id')->nullable()->constrained();
            $table->foreignId('destino_id')->nullable()->constrained();
            $table->integer('cantidad');
            $table->string('estado', 20)->default('abierta'); // abierta | resuelta
            $table->string('descripcion')->nullable();
            $table->string('resolucion')->nullable();
            $table->foreignId('reporto_id')->nullable()->constrained('users');
            $table->foreignId('resolvio_id')->nullable()->constrained('users');
            $table->timestamp('resuelta_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias');
        Schema::dropIfExists('movimientos');
        Schema::dropIfExists('existencias');
    }
};

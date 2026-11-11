<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('pedimento_id')->constrained();
            $table->string('origen')->default('Excel del propietario');
            $table->date('fecha_esperada');
            // esperada | en_conteo | cerrada
            $table->string('estado', 20)->default('esperada');
            $table->foreignId('cerro_id')->nullable()->constrained('users');
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recepcion_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('recepciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained();
            $table->unsignedInteger('cajas_esperadas');
            $table->unsignedInteger('piezas_por_caja');
            $table->unsignedInteger('piezas_esperadas');
            $table->unsignedInteger('piezas_leidas')->nullable(); // lectura RFID
            $table->unsignedInteger('piezas_confirmadas')->nullable(); // confirmación del operador
            $table->unsignedInteger('piezas_sin_accesorio')->default(0);
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones'); // dónde se guardó
            $table->timestamp('ubicada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('propietario');
            $table->date('fecha_compromiso');
            // nuevo | en_surtido | en_separacion | en_revision | listo | despachado
            $table->string('estado', 20)->default('nuevo');
            $table->timestamps();
        });

        Schema::create('pedido_destinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destino_id')->constrained();
            $table->boolean('etiqueta_importacion')->default(false);
            $table->boolean('etiqueta_comercial')->default(false);
            $table->boolean('sensor')->default(false);
            $table->unsignedInteger('bultos')->default(0);
            // pendiente | separado | verificado | con_diferencia
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('verificado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pedido_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pedido_destino_id')->constrained('pedido_destinos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained();
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('cantidad_separada')->nullable();
            $table->unsignedInteger('cantidad_leida')->nullable(); // lectura RFID en revisión
            $table->timestamps();
        });

        // Instrucciones de toma: de qué ubicación y pedimento sale cada pieza.
        Schema::create('tareas_surtido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('orden');
            $table->foreignId('producto_id')->constrained();
            $table->foreignId('pedimento_id')->constrained();
            $table->foreignId('ubicacion_id')->constrained('ubicaciones');
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('cantidad_tomada')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | tomada | con_faltante
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamp('tomada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('salidas', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('pedido_id')->constrained();
            $table->string('transportista');
            $table->string('placas', 15);
            $table->string('sello', 20);
            $table->unsignedInteger('piezas');
            $table->foreignId('autorizo_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salidas');
        Schema::dropIfExists('tareas_surtido');
        Schema::dropIfExists('pedido_lineas');
        Schema::dropIfExists('pedido_destinos');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('recepcion_lineas');
        Schema::dropIfExists('recepciones');
    }
};

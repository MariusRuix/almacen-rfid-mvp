<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30)->unique();
            $table->string('referencia_interna', 20)->nullable(); // la referencia que asigna la responsable
            $table->string('descripcion');
            $table->string('upc', 20)->nullable();
            $table->string('epc_prefijo', 12); // prefijo EPC simulado (RFID de origen)
            $table->unsignedInteger('piezas_por_caja')->default(12);
            $table->string('accesorio')->nullable(); // p. ej. "cinturón", "funda"
            $table->timestamps();
        });

        Schema::create('pedimentos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 21)->unique(); // AA AD PPPP NNNNNNN
            $table->string('clave', 4)->default('A1');
            $table->string('aduana');
            $table->string('contenedor', 15)->nullable(); // ISO 6346: XXXU 123456 7
            $table->string('proveedor');
            $table->date('fecha_pago');
            $table->timestamps();
        });

        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            // rack | adicional | recepcion | preparacion | incidencia
            $table->string('zona', 20);
            $table->unsignedInteger('capacidad')->default(600);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('destinos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 12)->unique();
            $table->string('nombre');
            $table->string('tipo', 20); // tienda | almacen
            $table->boolean('requiere_etiqueta_comercial')->default(true);
            $table->boolean('requiere_sensor')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinos');
        Schema::dropIfExists('ubicaciones');
        Schema::dropIfExists('pedimentos');
        Schema::dropIfExists('productos');
    }
};

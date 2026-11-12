<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conteo de recepción caja por caja (las cajas no traen etiqueta RFID maestra). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->foreignId('termino_conteo_id')->nullable()->constrained('users');
            $table->timestamp('conteo_terminado_at')->nullable();
        });

        Schema::table('recepcion_lineas', function (Blueprint $table) {
            $table->unsignedInteger('piezas_danadas')->default(0);
            $table->unsignedInteger('piezas_sin_etiqueta')->default(0);
        });

        Schema::create('recepcion_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('recepciones')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('estado', 20)->default('leida'); // leida | anulada
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
            $table->unique(['recepcion_id', 'numero']);
        });

        Schema::create('recepcion_caja_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_caja_id')->constrained('recepcion_cajas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained();
            $table->unsignedInteger('piezas_leidas')->default(0);
            $table->unsignedInteger('sin_etiqueta')->default(0); // contadas a mano: el RFID no las ve
            $table->unsignedInteger('sin_accesorio')->default(0);
            $table->unsignedInteger('danadas')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_caja_lineas');
        Schema::dropIfExists('recepcion_cajas');
        Schema::table('recepcion_lineas', fn (Blueprint $t) => $t->dropColumn(['piezas_danadas', 'piezas_sin_etiqueta']));
        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('termino_conteo_id');
            $table->dropColumn('conteo_terminado_at');
        });
    }
};

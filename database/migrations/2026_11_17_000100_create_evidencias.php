<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fotos y documentos que respaldan un paso: llegada, caja, incidencia, bulto, carga y acuse. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);          // recepcion | caja | incidencia | destino | pedido
            $table->unsignedBigInteger('referencia_id');
            $table->string('momento', 20);       // llegada | caja | incidencia | bulto | carga | acuse
            $table->string('archivo');           // ruta en el disco local (privado)
            $table->string('nombre');
            $table->string('mime', 100);
            $table->unsignedInteger('tamano');
            $table->string('nota', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
            $table->index(['tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencias');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cómo se contó cada cosa: RFID, código de barras o a mano (sin lector). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_cajas', function (Blueprint $table) {
            $table->string('origen', 10)->default('rfid'); // rfid | codigo | manual
        });

        Schema::table('pedido_destinos', function (Blueprint $table) {
            $table->string('verificacion', 10)->nullable(); // rfid | manual
            $table->foreignId('verifico_id')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_destinos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verifico_id');
            $table->dropColumn('verificacion');
        });
        Schema::table('recepcion_cajas', fn (Blueprint $t) => $t->dropColumn('origen'));
    }
};

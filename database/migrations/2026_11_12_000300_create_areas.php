<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Áreas ligeras: agrupan ubicaciones y se pueden activar o desactivar completas (p. ej. temporada). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->string('descripcion', 160)->nullable();
            $table->boolean('activa')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::table('ubicaciones', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained('areas');
        });

        // Todo lo existente queda en el área principal.
        $id = DB::table('areas')->insertGetId([
            'nombre' => 'Bodega principal', 'orden' => 1, 'activa' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ubicaciones')->update(['area_id' => $id]);
    }

    public function down(): void
    {
        Schema::table('ubicaciones', fn (Blueprint $t) => $t->dropConstrainedForeignId('area_id'));
        Schema::dropIfExists('areas');
    }
};

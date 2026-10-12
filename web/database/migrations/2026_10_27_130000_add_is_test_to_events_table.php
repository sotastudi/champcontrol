<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evento de prueba: habilita en su panel la generación de categorías e inscripciones ficticias.
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_test')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('is_test'));
    }
};

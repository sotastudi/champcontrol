<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Logo de las cuentas de club (disco local, no público); se muestra junto al club en las vistas de tatami.
        Schema::table('users', fn (Blueprint $table) => $table->string('logo_path')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('logo_path'));
    }
};

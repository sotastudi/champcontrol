<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Color propio del club (#rrggbb) para colorear el número de sus participantes; si es null se propone uno automático.
        Schema::table('users', fn (Blueprint $table) => $table->string('club_color', 7)->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('club_color'));
    }
};

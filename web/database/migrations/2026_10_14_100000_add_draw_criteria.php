<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('draw_criteria', 30)->default('level_weight_club');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->string('draw_criteria', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('draw_criteria'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('draw_criteria'));
    }
};

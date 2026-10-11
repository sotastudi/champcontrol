<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('tatami_count')->default(1);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('tatami')->nullable();
            $table->unsignedSmallInteger('referees')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['tatami', 'referees']));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('tatami_count'));
    }
};

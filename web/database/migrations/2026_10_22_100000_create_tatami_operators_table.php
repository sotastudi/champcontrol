<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tatami_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tatami');
            $table->timestamps();

            $table->unique(['event_id', 'user_id', 'tatami']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tatami_operators');
    }
};

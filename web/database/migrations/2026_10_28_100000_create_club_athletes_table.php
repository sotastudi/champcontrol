<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plantel de competidores de un club (cuenta de usuario con rol club), reutilizable en todos los eventos.
        Schema::create('club_athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('birth_date');
            $table->string('sex', 10);
            $table->decimal('weight', 5, 1)->nullable();
            $table->string('level', 20);
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_contact')->nullable();
            $table->timestamp('guardian_consent_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_athletes');
    }
};

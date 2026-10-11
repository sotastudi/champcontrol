<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number')->nullable();          // orden de combate; null en pases
            $table->unsignedTinyInteger('round');                        // 1 = primera ronda
            $table->unsignedSmallInteger('position');                    // posición dentro de la ronda
            $table->boolean('is_bronze')->default(false);
            $table->unsignedTinyInteger('required_participants')->default(2); // 1 = pase
            $table->foreignId('slot_a_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('slot_b_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('next_bout_id')->nullable()->constrained('bouts')->nullOnDelete();
            $table->char('next_slot', 1)->nullable();
            $table->foreignId('loser_next_bout_id')->nullable()->constrained('bouts')->nullOnDelete();
            $table->char('loser_next_slot', 1)->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'round', 'position', 'is_bronze']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bouts');
    }
};

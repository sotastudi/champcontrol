<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('club', 150)->nullable();
            $table->date('birth_date');
            $table->string('sex', 10)->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('level', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'first_name', 'last_name', 'birth_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
    }
};

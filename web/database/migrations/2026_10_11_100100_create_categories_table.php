<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('modality', 100)->nullable();
            $table->string('sex', 10)->nullable();            // null = mixta / sin restricción
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->decimal('min_weight', 5, 2)->nullable();
            $table->decimal('max_weight', 5, 2)->nullable();
            $table->string('level', 100)->nullable();
            $table->string('bronze_rule', 30)->default('bronze_match');
            $table->boolean('show_fourth')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('bracket_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

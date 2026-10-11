<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name', 150);
            $table->string('discipline', 100);
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->string('venue', 200);
            $table->string('status', 30)->default('draft');
            $table->boolean('is_public')->default(false);
            $table->boolean('registration_form_enabled')->default(false);
            $table->timestamps();

            $table->index(['is_public', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

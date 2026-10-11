<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('rules_text')->nullable();
            $table->string('rules_pdf_path', 255)->nullable();
            $table->string('rules_pdf_name', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['rules_text', 'rules_pdf_path', 'rules_pdf_name']));
    }
};

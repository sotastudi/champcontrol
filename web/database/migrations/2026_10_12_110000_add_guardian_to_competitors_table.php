<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_contact', 255)->nullable();
            $table->timestamp('guardian_consent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn(['guardian_name', 'guardian_contact', 'guardian_consent_at']);
        });
    }
};

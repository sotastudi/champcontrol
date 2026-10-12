<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La modalidad pasa de texto libre a una lista cerrada (App\Enums\Modality); lo que no encaja es kumite.
        DB::table('categories')->whereNotIn('modality', ['kumite', 'tameshikari', 'exhibition', 'agreed_bout', 'kata'])
            ->orWhereNull('modality')->update(['modality' => 'kumite']);

        Schema::table('categories', function (Blueprint $table) {
            $table->string('modality', 100)->default('kumite')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('modality', 100)->nullable()->default(null)->change();
        });
    }
};

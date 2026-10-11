<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('withdrawal_type', 20)->nullable();   // 'withdrawn' | 'disqualified'
            $table->text('withdrawal_reason')->nullable();        // privado del organizador
            $table->timestamp('withdrawn_at')->nullable();
        });

        Schema::table('bouts', function (Blueprint $table) {
            $table->string('result_type', 20)->default('normal'); // 'normal' | 'walkover'
        });
    }

    public function down(): void
    {
        Schema::table('bouts', function (Blueprint $table) {
            $table->dropColumn('result_type');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['withdrawal_type', 'withdrawal_reason', 'withdrawn_at']);
        });
    }
};

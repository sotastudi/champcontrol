<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('dq_podium_rule', 30)->default('vacant');
            $table->string('unfought_withdrawal_rule', 30)->default('keep_place');
            $table->string('default_bronze_rule', 30)->default('bronze_match');
            $table->string('withdrawal_before_results_rule', 30)->default('cancel_and_regenerate');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['dq_podium_rule', 'unfought_withdrawal_rule', 'default_bronze_rule', 'withdrawal_before_results_rule']);
        });
    }
};

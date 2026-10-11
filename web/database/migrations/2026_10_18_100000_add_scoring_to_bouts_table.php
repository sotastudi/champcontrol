<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bouts', function (Blueprint $table) {
            $table->unsignedTinyInteger('a_wazari')->default(0);
            $table->unsignedTinyInteger('b_wazari')->default(0);
            $table->boolean('a_ippon')->default(false);
            $table->boolean('b_ippon')->default(false);
            $table->unsignedTinyInteger('a_warnings')->default(0);
            $table->unsignedTinyInteger('b_warnings')->default(0);
            $table->string('abandoned_slot', 1)->nullable();
            $table->json('judge_votes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bouts', function (Blueprint $table) {
            $table->dropColumn(['a_wazari', 'b_wazari', 'a_ippon', 'b_ippon', 'a_warnings', 'b_warnings', 'abandoned_slot', 'judge_votes']);
        });
    }
};

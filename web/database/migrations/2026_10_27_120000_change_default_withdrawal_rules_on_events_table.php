<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nuevos valores por defecto: al descalificar suben los puestos y el retirado que no combatió se queda sin puesto.
        // Los eventos ya creados conservan la regla que tienen.
        Schema::table('events', function (Blueprint $table) {
            $table->string('dq_podium_rule', 30)->default('shift_up')->change();
            $table->string('unfought_withdrawal_rule', 30)->default('no_place')->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('dq_podium_rule', 30)->default('vacant')->change();
            $table->string('unfought_withdrawal_rule', 30)->default('keep_place')->change();
        });
    }
};

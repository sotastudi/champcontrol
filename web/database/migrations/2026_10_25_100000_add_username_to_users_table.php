<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Las cuentas de controlador de tatami pueden entrar con usuario y contraseña, sin correo.
            $table->string('username', 30)->nullable()->unique();
            $table->string('email')->nullable()->change();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Código que el público necesita para ver resultados; lo genera la aplicación y lo reparte el organizador.
            $table->string('access_code', 8)->nullable()->unique();
        });

        // Los eventos que ya existían reciben su código.
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        foreach (DB::table('events')->whereNull('access_code')->pluck('id') as $id) {
            do {
                $code = '';
                for ($i = 0; $i < 4; $i++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
            } while (DB::table('events')->where('access_code', $code)->exists());

            DB::table('events')->where('id', $id)->update(['access_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['access_code']);
            $table->dropColumn('access_code');
        });
    }
};

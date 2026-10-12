<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El color lo asigna el organizador por evento (el mismo club puede llevar otro color en cada campeonato).
        Schema::table('event_clubs', fn (Blueprint $table) => $table->string('color', 7)->nullable());

        foreach (DB::table('users')->whereNotNull('club_color')->get(['id', 'club_color']) as $user) {
            DB::table('event_clubs')->where('user_id', $user->id)->update(['color' => $user->club_color]);
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('club_color'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('club_color', 7)->nullable());
        Schema::table('event_clubs', fn (Blueprint $table) => $table->dropColumn('color'));
    }
};

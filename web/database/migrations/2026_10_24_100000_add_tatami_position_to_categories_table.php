<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Orden de la categoría dentro de su tatami (1 = la primera en competir).
            $table->unsignedSmallInteger('tatami_position')->nullable();
        });

        // Las categorías que ya tenían tatami conservan su orden actual (por creación).
        $positions = [];
        foreach (DB::table('categories')->whereNotNull('tatami')->orderBy('event_id')->orderBy('tatami')->orderBy('id')->get(['id', 'event_id', 'tatami']) as $row) {
            $key = $row->event_id.'-'.$row->tatami;
            $positions[$key] = ($positions[$key] ?? 0) + 1;
            DB::table('categories')->where('id', $row->id)->update(['tatami_position' => $positions[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('tatami_position'));
    }
};

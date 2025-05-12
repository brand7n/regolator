<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('lat')->nullable();
            $table->decimal('lon')->nullable();
        });

        DB::table('events')
          ->where('event_tag', 'NVHHH1900')
          ->update([
              'lat' => 41.17809320205751,
              'lon' => -77.54957166090036,
          ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lon']);
        });
    }
};

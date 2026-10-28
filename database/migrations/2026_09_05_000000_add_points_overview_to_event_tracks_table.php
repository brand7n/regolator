<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_tracks', function (Blueprint $table) {
            $table->json('points_overview')->nullable()->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('event_tracks', function (Blueprint $table) {
            $table->dropColumn('points_overview');
        });
    }
};

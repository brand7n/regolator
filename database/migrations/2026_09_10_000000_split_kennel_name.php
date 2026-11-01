<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kennels', function (Blueprint $table) {
            $table->renameColumn('name', 'abbreviation');
        });

        Schema::table('kennels', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('abbreviation');
        });
    }

    public function down(): void
    {
        Schema::table('kennels', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });

        Schema::table('kennels', function (Blueprint $table) {
            $table->renameColumn('abbreviation', 'name');
        });
    }
};

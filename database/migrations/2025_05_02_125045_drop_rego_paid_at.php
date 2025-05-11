<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //DB::statement("ALTER TABLE your_table DROP COLUMN column_name");
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rego_paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

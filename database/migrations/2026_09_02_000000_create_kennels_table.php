<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kennels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('kennel_id')->nullable()->constrained()->nullOnDelete();
            $table->dropColumn('kennel');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('kennel')->nullable()->after('name');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kennel_id');
        });

        Schema::dropIfExists('kennels');
    }
};

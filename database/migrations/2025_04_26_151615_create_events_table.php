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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('kennel');
            $table->text('description');
            $table->string('event_photo_path', 2048)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('location');
            $table->json('properties')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('private')->default(true);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->constrained('events');
            $table->foreignId('order_id')->nullable()->change();
            $table->string('status')->nullable();
            $table->text('comment')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

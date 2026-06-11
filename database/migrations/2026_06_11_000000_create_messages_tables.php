<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('event_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('DRAFT');
            $table->json('recipient_filter');
            $table->json('include_profile_fields')->nullable();
            $table->json('include_event_fields')->nullable();
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
        });

        Schema::create('message_recipients', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('order_id')->constrained();
            $table->string('status')->default('PENDING');
            $table->boolean('is_test')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_recipients');
        Schema::dropIfExists('messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('credential_id');
            $table->text('public_key');
            $table->unsignedInteger('counter')->default(0);
            $table->timestamps();

            $table->unique('credential_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('webauthn_credentials');
    }
}; 
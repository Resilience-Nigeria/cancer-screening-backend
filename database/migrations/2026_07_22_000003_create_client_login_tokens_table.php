<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_login_tokens', function (Blueprint $table) {
            $table->id('tokenId');
            $table->string('token', 80)->unique();
            $table->string('clientId');
            $table->timestamp('expiresAt');
            $table->timestamp('lastUsedAt')->nullable();
            $table->timestamps();

            $table->foreign('clientId')->references('clientId')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_login_tokens');
    }
};

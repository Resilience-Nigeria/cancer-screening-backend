<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bloom_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('purpose', 20);                  // verify | reset
            $table->string('code_hash', 64);                // HMAC, never the code itself
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloom_otps');
    }
};

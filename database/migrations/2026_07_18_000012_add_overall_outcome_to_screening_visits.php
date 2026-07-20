<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('screening_visits', function (Blueprint $table) {
            $table->enum('overallOutcome', ['normal', 'low_suspicion', 'suspicious', 'urgent_referral'])->nullable();
            $table->text('outcomeNotes')->nullable();
            // For low_suspicion — "repeat screening in 6-12 months or per protocol"
            $table->date('repeatScreeningDate')->nullable();
            $table->unsignedBigInteger('outcomeClassifiedBy')->nullable();
            $table->timestamp('outcomeClassifiedAt')->nullable();

            $table->foreign('outcomeClassifiedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('screening_visits', function (Blueprint $table) {
            $table->dropForeign(['outcomeClassifiedBy']);
            $table->dropColumn([
                'overallOutcome', 'outcomeNotes', 'repeatScreeningDate',
                'outcomeClassifiedBy', 'outcomeClassifiedAt',
            ]);
        });
    }
};

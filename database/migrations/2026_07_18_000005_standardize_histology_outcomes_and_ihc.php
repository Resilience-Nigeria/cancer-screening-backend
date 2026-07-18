<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    /**
     * histologyResult existed on breast_screenings since the original
     * migration but was never wired into fillable/validation, so it was
     * always null. This widens it to the standardized malignant/benign
     * classification the recommendations doc calls for (keeping the
     * original positive/negative values valid too, in case any row
     * already used them) and adds IHC follow-up fields so a malignant
     * result can automatically prompt for immunohistochemistry and
     * kick off referral — see BreastScreeningController.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `breast_screenings`
            MODIFY `histologyResult`
            ENUM('negative','positive','malignant','benign')
            NULL
        ");

        Schema::table('breast_screenings', function (Blueprint $table) {
            $table->boolean('ihcRequested')->default(false)->after('histologyResult');
            $table->string('ihcResult')->nullable()->after('ihcRequested');
        });
    }

    public function down(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            $table->dropColumn(['ihcRequested', 'ihcResult']);
        });

        DB::statement("
            ALTER TABLE `breast_screenings`
            MODIFY `histologyResult`
            ENUM('negative','positive')
            NULL
        ");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cervical_screenings', function (Blueprint $table) {
            $table->enum('moreThanOnePartner', ['yes', 'no'])
                ->nullable()
                ->after('treatmentReferral');

            $table->unsignedTinyInteger('ageAtFirstIntercourse')
                ->nullable()
                ->after('moreThanOnePartner');

            $table->unsignedTinyInteger('numberOfChildbirths')
                ->nullable()
                ->after('ageAtFirstIntercourse');

            $table->enum('contraceptiveUse', [
                'none',
                'oral_contraceptives',
                'iud',
                'barrier_methods',
                'other',
            ])->nullable()->after('numberOfChildbirths');
        });
    }

    public function down(): void
    {
        Schema::table('cervical_screenings', function (Blueprint $table) {
            $table->dropColumn([
                'moreThanOnePartner',
                'ageAtFirstIntercourse',
                'numberOfChildbirths',
                'contraceptiveUse',
            ]);
        });
    }
};
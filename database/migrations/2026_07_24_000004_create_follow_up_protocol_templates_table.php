<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Defines WHEN follow-up visits should happen after treatment and
     * WHAT they should cover — configurable per cancer type rather than
     * hardcoded, since the spec explicitly calls for this to match
     * national or institutional protocols. Seeded with the spec's own
     * default schedule (1/3/6/12 months, then annually) applied to
     * "all" cancer types; an admin can add cancer-type-specific
     * overrides or change the timing without a code change.
     */
    public function up(): void
    {
        Schema::create('follow_up_protocol_templates', function (Blueprint $table) {
            $table->id('templateId');
            // 'all' applies to every cancer type unless a more specific
            // row exists for that type.
            $table->string('cancerType')->default('all');
            $table->unsignedInteger('monthsAfterTreatment');
            $table->text('activities');
            $table->boolean('isRecurringAnnually')->default(false);
            $table->boolean('isActive')->default(true);
            $table->timestamps();
        });

        $defaults = [
            ['monthsAfterTreatment' => 1, 'activities' => 'Wound review, treatment tolerance, symptom assessment'],
            ['monthsAfterTreatment' => 3, 'activities' => 'Clinical review, laboratory tests, imaging if indicated'],
            ['monthsAfterTreatment' => 6, 'activities' => 'Clinical examination, surveillance testing'],
            ['monthsAfterTreatment' => 12, 'activities' => 'Annual review and routine surveillance', 'isRecurringAnnually' => true],
        ];

        foreach ($defaults as $row) {
            DB::table('follow_up_protocol_templates')->insert([
                'cancerType' => 'all',
                'monthsAfterTreatment' => $row['monthsAfterTreatment'],
                'activities' => $row['activities'],
                'isRecurringAnnually' => $row['isRecurringAnnually'] ?? false,
                'isActive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_protocol_templates');
    }
};

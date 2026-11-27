<?php

namespace App\Models\Concerns;

/**
 * Add `use \App\Models\Concerns\BreastCbeExtras;` inside the BreastScreening model.
 *
 * Makes the new CBE fields (result per breast, physical changes, biopsy procedure, recommended next step)
 * fillable and saved, whatever the breast screening controller passes to the model — so the existing
 * POST /visits/{visit}/breast-screening keeps working without changes.
 */
trait BreastCbeExtras
{
    public const CBE_EXTRA_FIELDS = [
        'leftCbeResult', 'rightCbeResult', 'physicalChanges', 'physicalChangesOther',
        'biopsyProcedure', 'biopsyProcedureOther', 'recommendedNextStep',
    ];

    private const CBE_RESULTS = ['normal', 'lump_detected', 'lump_not_detected'];
    private const BREAST_CHANGES = [
        'nipple_discharge', 'nipple_retraction', 'skin_discoloration', 'skin_dimpling', 'skin_thickening',
        'skin_ulceration', 'visible_veins', 'asymmetry', 'swelling', 'other',
    ];
    private const BIOPSY_PROCEDURES = ['fna', 'core_needle', 'vacuum_assisted', 'excision', 'incision', 'punch', 'other'];
    private const NEXT_STEPS = ['breast_ultrasound', 'mammography'];

    public function initializeBreastCbeExtras(): void
    {
        $this->mergeFillable(self::CBE_EXTRA_FIELDS);
        $this->mergeCasts(['physicalChanges' => 'array']);
    }

    public static function bootBreastCbeExtras(): void
    {
        static::saving(function ($m) {
            $req = request();
            if (!$req) return;
            foreach (self::CBE_EXTRA_FIELDS as $f) {
                if ($req->exists($f)) $m->{$f} = $req->input($f);
            }
            // Keep values tidy (unknown codes are dropped rather than failing the whole save).
            foreach (['leftCbeResult', 'rightCbeResult'] as $f) {
                if ($m->{$f} !== null && !in_array($m->{$f}, self::CBE_RESULTS, true)) $m->{$f} = null;
            }
            if (is_array($m->physicalChanges)) {
                $m->physicalChanges = array_values(array_intersect($m->physicalChanges, self::BREAST_CHANGES)) ?: null;
            } elseif ($m->physicalChanges !== null) {
                $m->physicalChanges = null;
            }
            if ($m->biopsyProcedure !== null && !in_array($m->biopsyProcedure, self::BIOPSY_PROCEDURES, true)) $m->biopsyProcedure = null;
            if ($m->recommendedNextStep !== null && !in_array($m->recommendedNextStep, self::NEXT_STEPS, true)) $m->recommendedNextStep = null;
            foreach (['physicalChangesOther', 'biopsyProcedureOther'] as $f) {
                if ($m->{$f} !== null) $m->{$f} = mb_substr(trim((string) $m->{$f}), 0, 255) ?: null;
            }
        });
    }
}

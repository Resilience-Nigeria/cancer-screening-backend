<?php

namespace App\Support;

use App\Models\CervicalScreening;
use App\Models\Client;
use App\Models\ScreeningSample;
use App\Models\ScreeningVisit;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the HPV fields on the clinical screening record (cervical_screenings) and the
 * Samples & Test Results page (screening_samples) saying the same thing.
 *
 * The lab record is the source of truth: once a result has been released on the Samples page,
 * that result is copied into the screening record, and the screening page never overwrites it.
 * Nothing here throws - a sync problem must never block saving a screening or a result.
 */
class ScreeningLabSync
{
    private const TEST = 'hpv_dna';

    /** Sample page -> screening record. Called after an HPV result is released. */
    public static function mirrorToCervical(ScreeningSample $sample): void
    {
        if ($sample->testType !== self::TEST) {
            return;
        }

        try {
            $query = CervicalScreening::where('clientId', $sample->clientId);
            $cx = $sample->visitId
                ? (clone $query)->where('visitId', $sample->visitId)->latest('screeningId')->first()
                : null;
            // A sample recorded outside a visit goes to the client's most recent cervical record.
            $cx = $cx ?: $query->latest('screeningId')->first();
            if (!$cx) {
                return;
            }

            $fill = [
                'hpvSampleDate' => $sample->sampleDate,
                'hpvResultDate' => $sample->resultDate,
                'hpvResult'     => $sample->status === 'result_released' ? $sample->result : null,
                'hpvGenotype'   => $sample->status === 'result_released' ? $sample->resultDetail : null,
            ];
            if ($sample->status !== 'result_released') {
                // Only the sample date is known yet - don't blank a result the clinician typed.
                $fill = ['hpvSampleDate' => $sample->sampleDate];
            }

            $cx->forceFill($fill)->save();

            if (!$sample->visitId && $cx->visitId) {
                $sample->forceFill(['visitId' => $cx->visitId])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('ScreeningLabSync::mirrorToCervical failed: ' . $e->getMessage());
        }
    }

    /**
     * Screening record -> sample page. Called after the wizard saves the cervical findings.
     * Creates the sample row from the sample date, and fills in the result if the clinician entered one
     * (only when the lab has not already released a result of its own).
     */
    public static function fromVisit(ScreeningVisit $visit, $user): ?ScreeningSample
    {
        try {
            $cx = CervicalScreening::where('visitId', $visit->visitId)->latest('screeningId')->first();
            if (!$cx || !$cx->hpvSampleDate) {
                return null;
            }

            $client = Client::where('clientId', $visit->clientId)->first();
            $sampleDate = $cx->hpvSampleDate->toDateString();

            $sample = ScreeningSample::where('clientId', $visit->clientId)
                ->where('testType', self::TEST)
                ->where(function ($q) use ($visit, $sampleDate) {
                    $q->where('visitId', $visit->visitId)
                      ->orWhere(fn ($n) => $n->whereNull('visitId')->whereDate('sampleDate', $sampleDate));
                })
                ->orderByDesc('sampleId')
                ->first();

            if ($sample && $sample->status === 'result_released') {
                if (!$sample->visitId) {
                    $sample->forceFill(['visitId' => $visit->visitId])->save();
                }
                return $sample; // lab result wins
            }

            if (!$sample) {
                $sample = new ScreeningSample();
                $sample->forceFill([
                    'clientId'        => $visit->clientId,
                    'visitId'         => $visit->visitId,
                    'facilityId'      => $user->facilityId ?? $client?->facilityId,
                    'cancerType'      => 'cervical',
                    'testType'        => self::TEST,
                    'collectedBy'     => 'Entered on the clinical screening page',
                    'collectedById'   => $user->id ?? null,
                    'status'          => 'collected',
                ]);
            }

            $sample->forceFill(['visitId' => $visit->visitId, 'sampleDate' => $sampleDate]);

            $result = $cx->hpvResult;
            $resultDate = $cx->hpvResultDate?->toDateString();
            $valid = in_array($result, ['positive', 'negative', 'indeterminate'], true)
                && $resultDate
                && $resultDate >= $sampleDate
                && $resultDate <= now()->toDateString();

            if ($valid) {
                $severity = ['negative' => 'normal', 'positive' => 'abnormal', 'indeterminate' => 'neutral'][$result];
                $sample->forceFill([
                    'status'            => 'result_released',
                    'result'            => $result,
                    'resultSeverity'    => $severity,
                    'resultDetail'      => $result === 'positive' ? $cx->hpvGenotype : null,
                    'resultDate'        => $resultDate,
                    'resultEnteredById' => $user->id ?? null,
                    'resultMeta'        => ['source' => 'clinical_screening'],
                ]);
            }

            $sample->save();
            return $sample;
        } catch (\Throwable $e) {
            Log::warning('ScreeningLabSync::fromVisit failed: ' . $e->getMessage());
            return null;
        }
    }
}

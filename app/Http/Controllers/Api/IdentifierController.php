<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ScreeningSample;
use App\Models\ScreeningVisit;
use App\Support\ScreeningTestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * One patient, one ID (simulation report §7).
 * The NCSR Client ID is the patient's number everywhere; the other numbers (hospital number, lab number,
 * CBE / ultrasound / biopsy / HPV test codes …) are kept as service codes linked to it.
 *  - GET /identifiers/lookup?q=…        any of those numbers → the client it belongs to
 *  - GET /clients/{clientId}/identifiers every number linked to one client, in one list
 */
class IdentifierController extends Controller
{
    private function visible()
    {
        return auth('api')->user()->visibleFacilityIds(); // null = national
    }

    /** GET /identifiers/lookup?q= */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($q) < 3, 422, 'Type at least 3 characters.');
        $visible = $this->visible();
        $scope = fn ($query) => $visible === null ? $query : $query->whereIn('facilityId', $visible);
        $hits = collect();

        $byClient = $scope(Client::query())->where('clientId', $q)->limit(1)->get();
        foreach ($byClient as $c) $hits->push($this->hit($c, 'NCSR Client ID', $c->clientId));

        if (Schema::hasColumn('clients', 'hospitalNumber')) {
            foreach ($scope(Client::query())->where('hospitalNumber', $q)->limit(10)->get() as $c) {
                $hits->push($this->hit($c, 'Hospital number', $c->hospitalNumber));
            }
        }

        // Test / sample codes like CBE-000123, BUS-000045, BBX-000007 (prefix + the sample's number).
        if (preg_match('/^([A-Za-z]{2,5})-?0*(\d{1,9})$/', $q, $m)) {
            $s = $scope(ScreeningSample::query())->where('sampleId', (int) $m[2])->first();
            if ($s && strtoupper($s->sampleRef) === strtoupper($m[1]) . '-' . str_pad($m[2], 6, '0', STR_PAD_LEFT)) {
                if ($c = Client::where('clientId', $s->clientId)->first()) $hits->push($this->hit($c, $this->testLabel($s) . ' code', $s->sampleRef, $s));
            }
        }
        foreach ($scope(ScreeningSample::query())->where('labSampleNumber', $q)->limit(10)->get() as $s) {
            if ($c = Client::where('clientId', $s->clientId)->first()) $hits->push($this->hit($c, 'Lab number (' . $this->testLabel($s) . ')', $s->labSampleNumber, $s));
        }
        if (Schema::hasColumn('screening_visits', 'referralNumber')) {
            foreach ($scope(ScreeningVisit::query())->where('referralNumber', $q)->limit(10)->get() as $v) {
                if ($c = Client::where('clientId', $v->clientId)->first()) $hits->push($this->hit($c, 'Referral / request number', $q));
            }
        }

        return response()->json(['results' => $hits->unique(fn ($h) => $h['clientId'] . '|' . $h['matched'])->values()]);
    }

    /** GET /clients/{clientId}/identifiers */
    public function forClient(string $clientId): JsonResponse
    {
        $c = Client::where('clientId', $clientId)->firstOrFail();
        $visible = $this->visible();
        abort_if($visible !== null && !in_array($c->facilityId, $visible), 403, 'You cannot access this client');

        $samples = ScreeningSample::where('clientId', $c->clientId)->orderByDesc('sampleDate')->orderByDesc('sampleId')->limit(200)->get();
        $visits = ScreeningVisit::where('clientId', $c->clientId)->orderByDesc('visitDate')->limit(50)->get();

        return response()->json([
            'clientId' => $c->clientId,
            'fullName' => $c->fullName,
            'hospitalNumber' => $c->hospitalNumber ?? null,
            'nin' => $c->nin ?? null,
            'services' => $samples->map(fn ($s) => [
                'sampleId' => $s->sampleId,
                'test' => $this->testLabel($s),
                'testType' => $s->testType,
                'code' => $s->sampleRef,
                'labSampleNumber' => $s->labSampleNumber,
                'date' => optional($s->sampleDate)->toDateString(),
                'status' => $s->status,
                'visitId' => $s->visitId,
            ])->values(),
            'visits' => $visits->map(fn ($v) => [
                'visitId' => $v->visitId,
                'date' => optional($v->visitDate)->toDateString(),
                'referralNumber' => $v->referralNumber ?? null,
                'finished' => $v->overallOutcome !== null,
            ])->values(),
        ]);
    }

    private function testLabel(ScreeningSample $s): string
    {
        return ScreeningTestCatalog::find($s->testType)['label'] ?? $s->testType;
    }

    private function hit(Client $c, string $matched, ?string $value, ?ScreeningSample $s = null): array
    {
        return [
            'clientId' => $c->clientId, 'fullName' => $c->fullName, 'facilityId' => $c->facilityId,
            'matched' => $matched, 'value' => $value, 'sampleId' => $s?->sampleId,
        ];
    }
}

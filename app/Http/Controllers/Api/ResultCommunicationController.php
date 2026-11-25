<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResultCommunication;
use App\Models\ScreeningSample;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Results back to the client: when and how a released result was given back, and whether counselling was done. */
class ResultCommunicationController extends Controller
{
    private function sample(int $id): ScreeningSample
    {
        $s = ScreeningSample::findOrFail($id);
        abort_if(!auth('api')->user()->canAccessFacility($s->facilityId), 403, 'You cannot access this sample.');
        return $s;
    }

    /** GET /mobile/results/{sampleId}/communication */
    public function show(int $sampleId): JsonResponse
    {
        $this->sample($sampleId);
        $rows = ResultCommunication::where('sampleId', $sampleId)->orderByDesc('id')->get();
        $names = User::whereIn('id', $rows->pluck('userId')->filter())->get()->mapWithKeys(fn ($u) => [$u->id => trim("{$u->firstName} {$u->lastName}")]);
        return response()->json(['communications' => $rows->map(fn ($r) => $r->toArray() + ['recordedBy' => $names[$r->userId] ?? null])]);
    }

    /** POST /mobile/results/{sampleId}/communication { method, communicatedAt?, counselling, counselledBy?, clientUnderstood?, notes? } */
    public function store(Request $request, int $sampleId): JsonResponse
    {
        $s = $this->sample($sampleId);
        abort_if($s->status !== 'result_released', 422, 'This sample has no result yet.');
        $data = $request->validate([
            'method' => ['required', 'in:in_person,phone,sms,whatsapp,other'],
            'communicatedAt' => ['nullable', 'date'],
            'counselling' => ['required', 'boolean'],
            'counselledBy' => ['nullable', 'string', 'max:120'],
            'clientUnderstood' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $row = ResultCommunication::create($data + [
            'sampleId' => $s->sampleId, 'clientId' => $s->clientId,
            'communicatedAt' => $data['communicatedAt'] ?? now(), 'userId' => auth('api')->id(),
        ]);
        if (class_exists(\App\Models\ActivityLog::class)) {
            \App\Models\ActivityLog::record('result_communicated', "Result given back to client {$s->clientId} ({$data['method']}).", [
                'subjectType' => 'sample', 'subjectId' => $s->sampleId, 'clientId' => $s->clientId, 'facilityId' => $s->facilityId,
            ]);
        }
        return response()->json(['message' => 'Recorded.', 'communication' => $row], 201);
    }

    /** GET /mobile/results/communicated?clientId= → sampleIds already given back (for the client page) */
    public function forClient(Request $request): JsonResponse
    {
        return response()->json(['sampleIds' => ResultCommunication::where('clientId', (string) $request->query('clientId'))->pluck('sampleId')->unique()->values()]);
    }
}

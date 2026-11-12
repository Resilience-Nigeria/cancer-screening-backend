<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SampleDeletionRequest;
use App\Models\ScreeningSample;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Soft deletion of test samples / results.
 *  - Desk officers (anyone who isn't an admin) can only REQUEST a deletion; an admin approves or rejects it.
 *  - Admins can delete directly, but must confirm and give a reason.
 *  - Nothing is ever removed from the database: rows get deletedAt set, and admins can list and restore them.
 */
class SampleDeletionController extends Controller
{
    // Same admin roles that can correct a client's state/LGA: NICRAT_SUPER_ADMIN and NICRAT_ADMIN.
    private function isAdmin($user): bool
    {
        return $user->isSuperAdmin() || $user->isNicratStaff();
    }

    private function adminOnly($user): void
    {
        abort_if(!$this->isAdmin($user), 403, 'Only an admin can do this.');
    }

    private function userName($u): string
    {
        $name = $u->fullName ?? $u->name ?? null;
        if (!$name) $name = trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? ''));
        return $name ?: ($u->email ?? 'Unknown user');
    }

    private function names($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) return [];
        $key = (new User())->getKeyName();
        return User::whereIn($key, $ids)->get()->mapWithKeys(fn ($u) => [$u->getKey() => $this->userName($u)])->all();
    }

    /** GET /screening-samples/deletion-access */
    public function access(): JsonResponse
    {
        return response()->json(['isAdmin' => $this->isAdmin(auth('api')->user())]);
    }

    /** POST /screening-samples/{id}/deletion-request  { reason } — desk officer asks for a deletion. */
    public function requestDeletion(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        $sample = ScreeningSample::findOrFail($id);
        abort_if(!$user->canAccessFacility($sample->facilityId), 403, 'You cannot access this sample');

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        if (SampleDeletionRequest::where('sampleId', $id)->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'A deletion request for this record is already waiting for approval.'], 422);
        }

        $req = SampleDeletionRequest::create([
            'sampleId'      => $sample->sampleId,
            'clientId'      => $sample->clientId,
            'facilityId'    => $sample->facilityId,
            'requestedById' => $user->id,
            'reason'        => $data['reason'],
            'status'        => 'pending',
        ]);

        $this->logSample('sample_deletion_requested', "Deletion requested for {$this->sampleLabel($sample)} of client {$sample->clientId}.", $sample, ['reason' => $data['reason']]);

        return response()->json(['message' => 'Deletion request sent to an admin for approval.', 'request' => $req], 201);
    }

    /** DELETE /screening-samples/{id}  { reason, confirm: true } — direct delete: any user before a result exists, admins after. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        $sample = ScreeningSample::findOrFail($id);

        // A sample with no result yet can be deleted by anyone who can see it.
        // Once a result is recorded, only an admin can delete it (others send a deletion request).
        if ($sample->status === 'result_released') {
            $this->adminOnly($user);
        } else {
            abort_if(!$user->canAccessFacility($sample->facilityId), 403, 'You cannot access this sample');
        }

        $data = $request->validate([
            'reason'  => ['required', 'string', 'min:5', 'max:500'],
            'confirm' => ['required', 'accepted'],
        ], ['confirm.accepted' => 'Please confirm the deletion.']);

        DB::transaction(function () use ($sample, $user, $data) {
            $this->softDelete($sample, $user->id, $data['reason']);
            SampleDeletionRequest::where('sampleId', $sample->sampleId)->where('status', 'pending')->update([
                'status' => 'approved', 'decidedById' => $user->id, 'decidedAt' => now(), 'decisionNote' => 'Deleted directly by an admin.',
            ]);
        });

        $this->logSample('sample_deleted', "Deleted {$this->sampleLabel($sample)} of client {$sample->clientId}.", $sample, ['reason' => $data['reason']]);

        return response()->json(['message' => 'Record deleted. An admin can restore it from Deleted Records.']);
    }

    /** GET /screening-samples/deletion-requests?status=pending|approved|rejected|all */
    public function requests(Request $request): JsonResponse
    {
        $this->adminOnly(auth('api')->user());
        $status = $request->query('status', 'pending');

        $rows = SampleDeletionRequest::with(['sample.client:clientId,fullName', 'client:clientId,fullName'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('requestId')->limit(300)->get();

        $names = $this->names($rows->pluck('requestedById')->merge($rows->pluck('decidedById')));

        return response()->json(['requests' => $rows->map(fn ($r) => [
            'requestId'   => $r->requestId,
            'sampleId'    => $r->sampleId,
            'clientId'    => $r->clientId,
            'clientName'  => $r->client->fullName ?? null,
            'testType'    => $r->sample->testType ?? null,
            'sampleRef'   => $r->sample->labSampleNumber ?? null,
            'sampleDate'  => $r->sample->sampleDate ?? null,
            'sampleStatus' => $r->sample->status ?? null,
            'result'      => $r->sample->result ?? null,
            'reason'      => $r->reason,
            'status'      => $r->status,
            'requestedBy' => $names[$r->requestedById] ?? 'Unknown user',
            'requestedAt' => $r->created_at,
            'decidedBy'   => $r->decidedById ? ($names[$r->decidedById] ?? 'Unknown user') : null,
            'decidedAt'   => $r->decidedAt,
            'decisionNote' => $r->decisionNote,
        ])->values()]);
    }

    /** POST /screening-samples/deletion-requests/{requestId}/decide  { approve: bool, note? } */
    public function decide(Request $request, int $requestId): JsonResponse
    {
        $user = auth('api')->user();
        $this->adminOnly($user);
        $req = SampleDeletionRequest::where('status', 'pending')->findOrFail($requestId);

        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            'note'    => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($req, $user, $data) {
            if ($data['approve']) {
                $sample = ScreeningSample::find($req->sampleId);
                if ($sample) $this->softDelete($sample, $user->id, $req->reason);
            }
            $req->update([
                'status' => $data['approve'] ? 'approved' : 'rejected',
                'decidedById' => $user->id, 'decidedAt' => now(), 'decisionNote' => $data['note'] ?? null,
            ]);
        });

        $logged = ScreeningSample::withTrashed()->find($req->sampleId);
        if ($logged) {
            $this->logSample(
                $data['approve'] ? 'sample_deletion_approved' : 'sample_deletion_rejected',
                ($data['approve'] ? 'Approved deletion of ' : 'Rejected deletion of ') . "{$this->sampleLabel($logged)} of client {$logged->clientId}.",
                $logged,
                ['reason' => $req->reason, 'note' => $data['note'] ?? null, 'requestId' => $req->requestId]
            );
        }

        return response()->json(['message' => $data['approve'] ? 'Approved. The record was deleted.' : 'Request rejected.']);
    }

    /** GET /screening-samples/deleted — admin view of soft-deleted records. */
    public function deleted(): JsonResponse
    {
        $this->adminOnly(auth('api')->user());

        $rows = ScreeningSample::onlyTrashed()
            ->with(['client:clientId,fullName', 'facility:facilityId,facilityName'])
            ->orderByDesc('deletedAt')->limit(300)->get();

        $names = $this->names($rows->pluck('deletedById'));

        return response()->json(['samples' => $rows->map(fn ($s) => [
            'sampleId'   => $s->sampleId,
            'clientId'   => $s->clientId,
            'clientName' => $s->client->fullName ?? null,
            'facility'   => $s->facility->facilityName ?? null,
            'cancerType' => $s->cancerType,
            'testType'   => $s->testType,
            'sampleDate' => $s->sampleDate,
            'status'     => $s->status,
            'result'     => $s->result,
            'resultDate' => $s->resultDate,
            'deletedAt'  => $s->deletedAt,
            'deletedBy'  => $names[$s->deletedById] ?? null,
            'deleteReason' => $s->deleteReason,
        ])->values()]);
    }

    /** POST /screening-samples/{id}/restore */
    public function restore(int $id): JsonResponse
    {
        $this->adminOnly(auth('api')->user());
        $sample = ScreeningSample::onlyTrashed()->findOrFail($id);
        $sample->restore();
        $sample->forceFill(['deletedById' => null, 'deleteReason' => null])->save();

        $this->logSample('sample_restored', "Restored {$this->sampleLabel($sample)} of client {$sample->clientId}.", $sample);

        return response()->json(['message' => 'Record restored.']);
    }

    private function sampleLabel(ScreeningSample $s): string
    {
        $test = \App\Support\ScreeningTestCatalog::find($s->testType)['label'] ?? $s->testType;
        return "{$test} {$s->sampleRef}" . ($s->status === 'result_released' ? ' (with result)' : '');
    }

    private function logSample(string $action, string $text, ScreeningSample $s, array $details = []): void
    {
        ActivityLog::record($action, $text, [
            'subjectType' => 'sample',
            'subjectId'   => $s->sampleId,
            'clientId'    => $s->clientId,
            'facilityId'  => $s->facilityId,
            'details'     => array_merge(['sample' => $this->sampleLabel($s), 'status' => $s->status, 'result' => $s->result], $details),
        ]);
    }

    private function softDelete(ScreeningSample $sample, $userId, string $reason): void
    {
        $sample->forceFill(['deletedById' => $userId, 'deleteReason' => mb_substr($reason, 0, 500)])->save();
        $sample->delete();
    }
}

<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomNavigatorSignup;
use App\Services\Bloom\BloomCaseService;
use App\Services\Bloom\BloomMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admins verify community health workers who signed up in the Bloom app. */
class AdminNavigatorController extends Controller
{
    use CaseConcerns;

    /** GET /bloom-admin/navigators?status=pending|approved|rejected */
    public function index(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->canReview($me)) {
            return $this->deny();
        }
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';

        $base = $this->scopeFacilities(BloomNavigatorSignup::query(), $me);
        $summary = (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $rows = (clone $base)->where('status', $status)->with(['user', 'facility'])->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'status'  => true,
            'summary' => [
                'pending'  => (int) ($summary['pending'] ?? 0),
                'approved' => (int) ($summary['approved'] ?? 0),
                'rejected' => (int) ($summary['rejected'] ?? 0),
            ],
            'data'    => $rows->map(fn ($s) => [
                'id'         => $s->id,
                'status'     => $s->status,
                'note'       => $s->applicant_note,
                'profession' => $s->profession,
                'staffId'    => $s->staff_id,
                'reviewNote' => $s->review_note,
                'reviewedAt' => optional($s->reviewed_at)->toIso8601String(),
                'signedUpAt' => optional($s->created_at)->toIso8601String(),
                'user'       => $s->user ? [
                    'id'    => $s->user->id,
                    'name'  => trim("{$s->user->firstName} {$s->user->lastName}"),
                    'email' => $s->user->email,
                    'phone' => $s->user->phoneNumber,
                ] : null,
                'facility'   => $s->facility ? ['id' => (int) $s->facility->facilityId, 'name' => $s->facility->facilityName, 'state' => $s->facility->facilityState ?? null] : null,
            ])->values(),
        ]);
    }

    /** POST /bloom-admin/navigators/{id}/approve */
    public function approve(int $id, BloomMailService $mail): JsonResponse
    {
        $me = $this->staff();
        if (!$this->canReview($me)) {
            return $this->deny();
        }
        $s = $this->scopeFacilities(BloomNavigatorSignup::query(), $me)->with('user')->find($id);
        if (!$s || !$s->user) {
            return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Sign-up not found.'], 404);
        }

        $s->user->update(['status' => 'active']);
        $s->update(['status' => 'approved', 'review_note' => null, 'reviewed_by' => $me->id, 'reviewed_at' => now()]);
        BloomCaseService::assignWaiting((int) $s->facility_id);

        if ($s->user->email) {
            $mail->sendNotice($s->user->email, $s->user->firstName, 'Your Bloom health worker account is approved', [
                'Your community health worker account has been verified.',
                'Sign in to the Bloom app (choose Community Health Worker) or the NCSR web app with your email or phone number and password to see the women assigned to you.',
            ]);
        }

        return response()->json(['status' => true, 'message' => 'Approved. They can now sign in.']);
    }

    /** POST /bloom-admin/navigators/{id}/reject  { note } */
    public function reject(Request $request, int $id, BloomMailService $mail): JsonResponse
    {
        $me = $this->staff();
        if (!$this->canReview($me)) {
            return $this->deny();
        }
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:300']]);
        $s = $this->scopeFacilities(BloomNavigatorSignup::query(), $me)->with('user')->find($id);
        if (!$s || !$s->user) {
            return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Sign-up not found.'], 404);
        }

        $s->user->update(['status' => 'inactive']);
        $s->update(['status' => 'rejected', 'review_note' => trim($data['note']), 'reviewed_by' => $me->id, 'reviewed_at' => now()]);

        if ($s->user->email) {
            $mail->sendNotice($s->user->email, $s->user->firstName, 'About your Bloom health worker account', [
                'We could not verify your community health worker account yet.',
                'Reason: ' . trim($data['note']),
                'If you think this is a mistake, please contact your facility.',
            ]);
        }

        return response()->json(['status' => true, 'message' => 'Rejected. The applicant has been told why.']);
    }
}

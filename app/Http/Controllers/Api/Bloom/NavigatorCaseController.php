<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomCase;
use App\Services\Bloom\BloomCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The health worker's own cases: those assigned to them, plus unassigned ones at their facility
 * (working on one of those claims it). Only active NAVIGATOR users get in.
 */
class NavigatorCaseController extends Controller
{
    use CaseConcerns;

    /** GET /bloom-chw/cases */
    public function index(): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny('Only verified community health workers can see cases.');
        }

        BloomCaseService::sync();

        $rows = $this->mine($me)->with(['appointment', 'account', 'ncsrClient', 'facility', 'navigator'])
            ->orderByRaw('closed_at IS NULL DESC')->orderByDesc('id')->limit(300)->get();

        return response()->json(['status' => true, 'data' => $rows->map(fn ($c) => $this->presentCase($c, true))->values()]);
    }

    /** GET /bloom-chw/cases/{id} */
    public function show(int $id): JsonResponse
    {
        return $this->act($id, fn (BloomCase $c) => null);
    }

    /** POST /bloom-chw/cases/{id}/start — opening a new case starts the community follow-up. */
    public function start(int $id): JsonResponse
    {
        return $this->act($id, function (BloomCase $c) {
            if ($c->step_community === 'not-started' && !$c->closed_at) {
                $c->step_community = 'in-progress';
            }
        });
    }

    /** PATCH /bloom-chw/cases/{id}/notes */
    public function notes(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        return $this->act($id, function (BloomCase $c) use ($data) {
            $c->notes = isset($data['notes']) ? trim($data['notes']) : null;
        });
    }

    /** POST /bloom-chw/cases/{id}/community */
    public function community(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        return $this->act($id, function (BloomCase $c) use ($data) {
            if (array_key_exists('notes', $data)) {
                $c->notes = $data['notes'] !== null ? trim($data['notes']) : null;
            }
            $c->step_community = 'completed';
            if ($c->step_phc === 'not-started') {
                $c->step_phc = 'in-progress';
            }
        });
    }

    /** POST /bloom-chw/cases/{id}/phc  { outcome: no-change | concerning-change } */
    public function phc(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['outcome' => ['required', 'in:no-change,concerning-change']]);

        return $this->act($id, function (BloomCase $c) use ($data) {
            if ($c->step_community !== 'completed') {
                return 'Finish the community follow-up first.';
            }
            $c->outcome  = $data['outcome'];
            $c->step_phc = 'completed';
            if ($data['outcome'] === 'concerning-change') {
                $c->step_referral = 'in-progress';
                $c->step_followup = 'not-started';
                $c->closed_at     = null;
            } else {
                $c->step_referral = 'not-needed';
                $c->step_followup = 'not-needed';
                $c->closed_at     = now();
            }
        });
    }

    /** POST /bloom-chw/cases/{id}/referral  { facility, date } */
    public function referral(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'facilityId' => ['nullable', 'integer'],
            'facility'   => ['required_without:facilityId', 'nullable', 'string', 'max:191'],
            'date'       => ['required', 'date_format:Y-m-d'],
        ]);

        // The app picks from the NCSR facility list; store that facility's real name.
        $name = null;
        if (!empty($data['facilityId'])) {
            $name = \App\Models\Facility::where('facilityId', $data['facilityId'])->value('facilityName');
            if (!$name) {
                return response()->json(['status' => false, 'code' => 'FACILITY_NOT_FOUND', 'message' => 'Choose the facility from the list.'], 422);
            }
        }
        $name = $name ?? trim((string) $data['facility']);

        return $this->act($id, function (BloomCase $c) use ($data, $name) {
            if ($c->outcome !== 'concerning-change' || $c->step_phc !== 'completed') {
                return 'A referral is only needed after a concerning PHC result.';
            }
            $c->referral_facility = $name;
            $c->referral_date     = trim($data['date']);
            $c->step_referral     = 'completed';
            if ($c->step_followup === 'not-started') {
                $c->step_followup = 'in-progress';
            }
        });
    }

    /** POST /bloom-chw/cases/{id}/followup  { date? } */
    public function followup(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return $this->act($id, function (BloomCase $c) use ($data) {
            if ($c->step_referral !== 'completed') {
                return 'Complete the referral step first.';
            }
            $c->follow_up_date = isset($data['date']) ? trim($data['date']) : null;
            $c->step_followup  = 'completed';
            $c->closed_at      = now();
        });
    }

    /** Cases this health worker may see. */
    private function mine($me)
    {
        return BloomCase::query()->where(function ($q) use ($me) {
            $q->where('navigator_id', $me->id)
              ->orWhere(fn ($w) => $w->whereNull('navigator_id')->where('facility_id', $me->facilityId));
        });
    }

    /** Loads a visible case, applies a change (closure may return an error message), claims it if unassigned. */
    private function act(int $id, callable $change): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny('Only verified community health workers can see cases.');
        }

        $case = $this->mine($me)->with(['appointment', 'account', 'ncsrClient', 'facility', 'navigator'])->find($id);
        if (!$case) {
            return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Case not found.'], 404);
        }

        $error = $change($case);
        if (is_string($error)) {
            return response()->json(['status' => false, 'code' => 'WRONG_STEP', 'message' => $error], 422);
        }

        if ($case->isDirty()) {
            if (!$case->navigator_id) {
                $case->navigator_id = $me->id;
                $case->assigned_at  = now();
            }
            $case->save();
            $case->load('navigator');
        }

        return response()->json(['status' => true, 'data' => $this->presentCase($case, true)]);
    }
}

<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomAppointmentRequest;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Appointment requests from the app. A request is a preferred date and time;
 * the centre or a navigator confirms the real slot afterwards.
 */
class AppointmentController extends Controller
{
    use Concerns;

    /** GET /bloom/appointments */
    public function index(): JsonResponse
    {
        $rows = BloomAppointmentRequest::with('facility')
            ->where('account_id', $this->account()->id)
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json(['status' => true, 'data' => $rows->map(fn ($r) => $this->present($r))->values()]);
    }

    /** POST /bloom/appointments */
    public function store(Request $request): JsonResponse
    {
        $maxDays = (int) config('bloom.appointments.max_days_ahead');

        $data = $request->validate([
            'facility_id'    => ['required', 'integer', 'exists:facilities,facilityId'],
            'type'           => ['nullable', 'string', Rule::in(config('bloom.appointments.types'))],
            'preferred_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:' . now()->addDays($maxDays)->toDateString()],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'note'           => ['nullable', 'string', 'max:500'],
        ]);

        $facility = Facility::where('facilityId', $data['facility_id'])->first();
        if (!$facility || $facility->status !== 'active') {
            return $this->fail('CENTRE_UNAVAILABLE', 'That centre is not taking requests right now.', 422);
        }

        $account = $this->account();

        $pending = BloomAppointmentRequest::where('account_id', $account->id)->where('status', 'pending');

        if ((clone $pending)->count() >= (int) config('bloom.appointments.max_pending')) {
            return $this->fail('TOO_MANY_PENDING', 'You already have several requests waiting. Please wait for those to be confirmed or cancel one.', 422);
        }

        if ((clone $pending)->where('facility_id', $facility->facilityId)->whereDate('preferred_date', $data['preferred_date'])->exists()) {
            return $this->fail('DUPLICATE_REQUEST', 'You already asked this centre for that day.', 422);
        }

        $appt = BloomAppointmentRequest::create([
            'account_id'     => $account->id,
            'facility_id'    => $facility->facilityId,
            'type'           => $data['type'] ?? 'general',
            'preferred_date' => $data['preferred_date'],
            'preferred_time' => $data['preferred_time'] ?? null,
            'note'           => isset($data['note']) ? trim($data['note']) : null,
            'status'         => 'pending',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Request sent.',
            'data'    => $this->present($appt->load('facility')),
        ], 201);
    }

    /** POST /bloom/appointments/{id}/cancel */
    public function cancel(int $id): JsonResponse
    {
        $row = BloomAppointmentRequest::where('account_id', $this->account()->id)->find($id);

        if (!$row) {
            return $this->fail('NOT_FOUND', 'Request not found.', 404);
        }
        if (!in_array($row->status, ['pending', 'confirmed'], true)) {
            return $this->fail('NOT_CANCELLABLE', 'This request can no longer be cancelled.', 422);
        }

        $row->update(['status' => 'cancelled']);

        return response()->json(['status' => true, 'message' => 'Request cancelled.', 'data' => $this->present($row->load('facility'))]);
    }

    private function present(BloomAppointmentRequest $r): array
    {
        $f = $r->facility;

        return [
            'id'       => $r->id,
            'status'   => $r->status,
            'type'     => $r->type,
            'date'     => $r->preferred_date->toDateString(),
            'time'     => $r->preferred_time,
            'note'     => $r->note,
            'centre'   => $f ? [
                'id'      => (int) $f->facilityId,
                'name'    => $f->facilityName,
                'address' => $f->facilityAddress,
                'phone'   => $f->phoneNumber ?: null,
            ] : null,
            'createdAt' => optional($r->created_at)->toIso8601String(),
        ];
    }
}

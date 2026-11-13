<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomAppointmentRequest;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Staff view of Bloom app bookings. A facility user sees only their facility's
 * requests (visibleFacilityIds), a national user sees everything.
 */
class AdminAppointmentController extends Controller
{
    private const STATUSES = ['pending', 'confirmed', 'declined', 'cancelled', 'completed'];

    /** GET /bloom-admin/appointments */
    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'status'     => ['nullable', 'string'],
            'facilityId' => ['nullable', 'integer'],
            'search'     => ['nullable', 'string', 'max:100'],
            'dateFrom'   => ['nullable', 'date_format:Y-m-d'],
            'dateTo'     => ['nullable', 'date_format:Y-m-d'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'perPage'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $scope = $this->scope();

        $base = BloomAppointmentRequest::query();
        if ($scope !== null) {
            $base->whereIn('facility_id', $scope ?: [0]);
        }
        if (!empty($v['facilityId'])) {
            $base->where('facility_id', (int) $v['facilityId']);
        }
        if (!empty($v['dateFrom'])) {
            $base->whereDate('preferred_date', '>=', $v['dateFrom']);
        }
        if (!empty($v['dateTo'])) {
            $base->whereDate('preferred_date', '<=', $v['dateTo']);
        }
        if (!empty($v['search'])) {
            $term = trim($v['search']);
            $digits = preg_replace('/\D+/', '', $term);
            $base->whereHas('account', function ($q) use ($term, $digits) {
                $q->where(function ($w) use ($term, $digits) {
                    $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                    if ($digits !== '') {
                        $w->orWhere('phone', 'like', '%' . ltrim($digits, '0') . '%');
                    }
                });
            });
        }

        // Counts for the summary tiles ignore the status filter, so the tiles stay stable.
        $summary = array_fill_keys(self::STATUSES, 0);
        (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')
            ->each(function ($n, $status) use (&$summary) {
                $summary[$status] = (int) $n;
            });
        $summary['all'] = array_sum($summary);

        $list = clone $base;
        if (!empty($v['status']) && in_array($v['status'], self::STATUSES, true)) {
            $list->where('status', $v['status']);
        }

        $perPage = (int) ($v['perPage'] ?? 20);
        $page    = (int) ($v['page'] ?? 1);
        $total   = (clone $list)->count();

        // Waiting requests first (oldest date first), then everything else newest first.
        $rows = $list->with(['account', 'facility'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'pending' THEN preferred_date END ASC")
            ->orderByDesc('id')
            ->skip(($page - 1) * $perPage)->take($perPage)->get();

        // Facilities that have bookings (for the filter drop-down).
        $facilityIds = (clone $base)->distinct()->pluck('facility_id')->all();
        $facilities  = $facilityIds
            ? Facility::whereIn('facilityId', $facilityIds)->orderBy('facilityName')->get(['facilityId', 'facilityName'])
                ->map(fn ($f) => ['id' => (int) $f->facilityId, 'name' => $f->facilityName])->values()
            : [];

        return response()->json([
            'status'       => true,
            'facilities'   => $facilities,
            'appointments' => $rows->map(fn ($r) => $this->present($r))->values(),
            'total'        => $total,
            'summary'      => $summary,
        ]);
    }

    /** POST /bloom-admin/appointments/{id}/confirm */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $row = $this->find($id);
        if (!$row) {
            return $this->notFound();
        }
        if (!in_array($row->status, ['pending', 'confirmed'], true)) {
            return $this->wrongState($row);
        }

        $maxDays = (int) config('bloom.appointments.max_days_ahead');
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:' . now()->addDays($maxDays + 30)->toDateString()],
            'time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $row->update([
            'status'         => 'confirmed',
            'confirmed_date' => $data['date'],
            'confirmed_time' => $data['time'] ?? null,
            'staff_note'     => isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null,
            'decided_by'     => Auth::guard('api')->id(),
            'decided_at'     => now(),
        ]);

        \App\Services\Bloom\BloomPushService::toAccount((int) $row->account_id, 'Your booking is confirmed', trim(($row->facility->facilityName ?? 'The centre') . ' confirmed your visit for ' . \Carbon\Carbon::parse($data['date'])->format('D j M') . (!empty($data['time']) ? ' at ' . $data['time'] : '') . '.'), ['href' => '/find']);
        $this->tellNavigator($row, 'Booking confirmed', 'Confirmed for ' . $data['date'] . (!empty($data['time']) ? ' at ' . $data['time'] : '') . '.');

        return $this->ok($row, 'Booking confirmed.');
    }

    /** POST /bloom-admin/appointments/{id}/decline */
    public function decline(Request $request, int $id): JsonResponse
    {
        $row = $this->find($id);
        if (!$row) {
            return $this->notFound();
        }
        if (!in_array($row->status, ['pending', 'confirmed'], true)) {
            return $this->wrongState($row);
        }

        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);

        $row->update([
            'status'         => 'declined',
            'confirmed_date' => null,
            'confirmed_time' => null,
            'staff_note'     => trim($data['note']),
            'decided_by'     => Auth::guard('api')->id(),
            'decided_at'     => now(),
        ]);

        \App\Services\Bloom\BloomCaseService::closeIfWithdrawn($row);
        \App\Services\Bloom\BloomPushService::toAccount((int) $row->account_id, 'The centre could not take your booking', trim($data['note']), ['href' => '/find']);
        $this->tellNavigator($row, 'Booking declined', 'The centre declined the booking: ' . trim($data['note']));

        return $this->ok($row, 'Booking declined.');
    }

    /** POST /bloom-admin/appointments/{id}/complete */
    public function complete(int $id): JsonResponse
    {
        $row = $this->find($id);
        if (!$row) {
            return $this->notFound();
        }
        if ($row->status !== 'confirmed') {
            return response()->json(['status' => false, 'code' => 'WRONG_STATE', 'message' => 'Only a confirmed booking can be marked as attended.'], 422);
        }

        $row->update(['status' => 'completed', 'decided_by' => Auth::guard('api')->id(), 'decided_at' => now()]);

        return $this->ok($row, 'Marked as attended.');
    }

    /** Lets the health worker following this woman know what the centre decided. */
    private function tellNavigator(BloomAppointmentRequest $r, string $title, string $body): void
    {
        $case = \App\Models\BloomCase::where('appointment_id', $r->id)->first();
        if ($case && $case->navigator_id) {
            \App\Services\Bloom\BloomStaffNotifier::notify((int) $case->navigator_id, 'booking', "{$title}: {$case->code()}", $body, $case->id);
        }
    }

    /** null = national (all facilities), otherwise the facility ids this staff member may see. */
    private function scope(): ?array
    {
        $user = Auth::guard('api')->user();
        if (!$user || !method_exists($user, 'visibleFacilityIds')) {
            return [];
        }
        $ids = $user->visibleFacilityIds();

        return $ids === null ? null : array_values(array_map('intval', (array) $ids));
    }

    private function find(int $id): ?BloomAppointmentRequest
    {
        $q = BloomAppointmentRequest::with(['account', 'facility']);
        $scope = $this->scope();
        if ($scope !== null) {
            $q->whereIn('facility_id', $scope ?: [0]);
        }

        return $q->find($id);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Booking not found.'], 404);
    }

    private function wrongState(BloomAppointmentRequest $r): JsonResponse
    {
        return response()->json(['status' => false, 'code' => 'WRONG_STATE', 'message' => "This booking is already {$r->status}."], 422);
    }

    private function ok(BloomAppointmentRequest $r, string $message): JsonResponse
    {
        return response()->json(['status' => true, 'message' => $message, 'appointment' => $this->present($r->fresh(['account', 'facility']))]);
    }

    private function present(BloomAppointmentRequest $r): array
    {
        $a = $r->account;
        $f = $r->facility;

        return [
            'id'            => $r->id,
            'status'        => $r->status,
            'type'          => $r->type,
            'preferredDate' => optional($r->preferred_date)->toDateString(),
            'preferredTime' => $r->preferred_time,
            'note'          => $r->note,
            'confirmedDate' => optional($r->confirmed_date)->toDateString(),
            'confirmedTime' => $r->confirmed_time,
            'staffNote'     => $r->staff_note,
            'decidedAt'     => optional($r->decided_at)->toIso8601String(),
            'rescheduled'   => (int) $r->reschedule_count,
            'requestedAt'   => optional($r->created_at)->toIso8601String(),
            'client'        => $a ? [
                'name'  => $a->name,
                'phone' => $a->phone,
                'email' => $a->email,
                'age'   => $a->age,
            ] : null,
            'facility'      => $f ? [
                'id'    => (int) $f->facilityId,
                'name'  => $f->facilityName,
                'state' => $f->facilityState ?? null,
            ] : null,
        ];
    }
}

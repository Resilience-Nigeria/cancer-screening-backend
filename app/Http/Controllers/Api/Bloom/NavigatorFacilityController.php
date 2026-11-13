<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facility directory for health workers: active NCSR facilities with their contact details, including the
 * facility's focal person (navigator name/phone/email), which the public Bloom app does not show.
 * Her own facility first, then her LGA, then her state.
 */
class NavigatorFacilityController extends Controller
{
    use CaseConcerns;

    /** GET /bloom-chw/facility-directory?search=&area=state|all&type=screening|treatment */
    public function index(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny('Only verified community health workers can see the facility directory.');
        }

        $v = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'area'   => ['nullable', 'in:state,all'],
            'type'   => ['nullable', 'in:screening,treatment'],
        ]);
        $term = trim((string) ($v['search'] ?? ''));
        $area = $v['area'] ?? 'state';
        $mine = Facility::where('facilityId', $me->facilityId)->first();

        $q = Facility::query()->where('status', 'active');
        if ($term !== '') {
            $q->where(function ($w) use ($term) {
                $w->where('facilityName', 'like', "%{$term}%")
                  ->orWhere('facilityLga', 'like', "%{$term}%")
                  ->orWhere('facilityState', 'like', "%{$term}%")
                  ->orWhere('facilityAddress', 'like', "%{$term}%");
            });
        }
        if ($area === 'state' && $mine && $mine->facilityState && $term === '') {
            $q->where('facilityState', $mine->facilityState);
        }
        if (($v['type'] ?? null) === 'screening') {
            $q->where('isScreeningCenter', true);
        } elseif (($v['type'] ?? null) === 'treatment') {
            $q->where('isTreatmentCenter', true);
        }

        // Her facility, then same LGA, then same state, then by name.
        if ($mine) {
            $q->orderByRaw('facilityId = ? DESC', [$mine->facilityId])
              ->orderByRaw('facilityLga = ? DESC', [(string) $mine->facilityLga])
              ->orderByRaw('facilityState = ? DESC', [(string) $mine->facilityState]);
        }
        $rows = $q->orderBy('facilityName')->limit(300)->get();

        return response()->json([
            'status' => true,
            'data'   => $rows->map(fn (Facility $f) => $this->present($f, $mine))->values(),
            'myFacilityId' => $mine ? (int) $mine->facilityId : null,
        ]);
    }

    private function present(Facility $f, ?Facility $mine): array
    {
        $address = implode(', ', array_unique(array_filter([
            trim((string) $f->facilityAddress), trim((string) $f->facilityLga), trim((string) $f->facilityState),
        ], fn ($p) => $p !== '')));

        $kinds = [];
        if ($f->isScreeningCenter) {
            $kinds[] = 'Screening';
        }
        if ($f->isTreatmentCenter) {
            $kinds[] = 'Treatment';
        }

        return [
            'id'        => (int) $f->facilityId,
            'name'      => (string) $f->facilityName,
            'isMine'    => $mine && (int) $mine->facilityId === (int) $f->facilityId,
            'address'   => $address ?: null,
            'lga'       => $f->facilityLga,
            'state'     => $f->facilityState,
            'level'     => $f->facilityLevel ?? null,
            'kinds'     => $kinds,
            'phone'     => $f->phoneNumber ?: null,
            'whatsapp'  => $f->whatsappNumber ?: null,
            'email'     => $f->email ?: null,
            'hours'     => method_exists($f, 'formatClinicHours') ? $f->formatClinicHours() : null,
            'lat'       => $f->latitude,
            'lng'       => $f->longitude,
            // Focal person (staff only).
            'contactPerson' => ($f->navigatorName || $f->navigatorPhone || $f->navigatorEmail) ? [
                'name'  => $f->navigatorName ?: null,
                'phone' => $f->navigatorPhone ?: null,
                'email' => $f->navigatorEmail ?: null,
            ] : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Support\Bloom\CentrePresenter;
use App\Support\Bloom\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public "Find a centre" endpoints for the Bloom app (no sign-in needed, so
 * people can look before they create an account).
 */
class CentreController extends Controller
{
    /**
     * GET /bloom/centres
     *   lat, lng       person's position (optional; enables distance + nearest-first)
     *   radius_km      search radius around lat/lng (default config, max 500)
     *   q              text search: name, address, LGA, state
     *   state          exact state filter
     *   type           screening | treatment | all   (default config)
     *   open_now       1 = only centres that are open right now
     *   limit          1-50 (default 20)
     */
    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'lat'       => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng'       => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'q'         => ['nullable', 'string', 'max:80'],
            'state'     => ['nullable', 'string', 'max:60'],
            'type'      => ['nullable', 'in:screening,treatment,all'],
            'open_now'  => ['nullable', 'boolean'],
            'limit'     => ['nullable', 'integer', 'min:1'],
        ]);

        $max    = (int) config('bloom.centres.max_limit');
        $limit  = min($max, (int) ($v['limit'] ?? 20));
        $type   = $v['type'] ?? config('bloom.centres.default_type');
        $hasPos = isset($v['lat'], $v['lng']);
        $radius = (float) ($v['radius_km'] ?? config('bloom.centres.default_radius_km'));
        $tz     = new \DateTimeZone((string) config('bloom.centres.timezone'));
        $now    = new \DateTimeImmutable('now', $tz);

        $query = $this->baseQuery($type);

        if (!empty($v['state'])) {
            $query->whereRaw('LOWER(TRIM(facilityState)) = ?', [mb_strtolower(trim($v['state']))]);
        }

        if (!empty($v['q'])) {
            $like = '%' . addcslashes(trim($v['q']), '%_\\') . '%';
            $query->where(function ($w) use ($like) {
                $w->where('facilityName', 'like', $like)
                  ->orWhere('facilityAddress', 'like', $like)
                  ->orWhere('facilityLga', 'like', $like)
                  ->orWhere('facilityState', 'like', $like);
            });
        }

        if ($hasPos) {
            $box = Geo::boundingBox((float) $v['lat'], (float) $v['lng'], $radius);
            $query->whereBetween('latitude', [$box['minLat'], $box['maxLat']])
                  ->whereBetween('longitude', [$box['minLng'], $box['maxLng']]);
        }

        $facilities = $query->get();
        $this->loadServices($facilities);

        $rows = [];
        foreach ($facilities as $f) {
            $km = $hasPos
                ? Geo::distanceKm((float) $v['lat'], (float) $v['lng'], (float) $f->latitude, (float) $f->longitude)
                : null;

            if ($km !== null && $km > $radius) {
                continue;                                   // inside the square, outside the circle
            }

            $row = CentrePresenter::toArray($f, $km, $now);

            if ($request->boolean('open_now') && $row['openNow'] !== true) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, $hasPos
            ? fn ($a, $b) => $a['distanceKm'] <=> $b['distanceKm']
            : fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        $total = count($rows);

        return response()->json([
            'status' => true,
            'data'   => array_slice($rows, 0, $limit),
            'meta'   => [
                'total'     => $total,
                'returned'  => min($total, $limit),
                'radius_km' => $hasPos ? $radius : null,
                'origin'    => $hasPos ? ['lat' => (float) $v['lat'], 'lng' => (float) $v['lng']] : null,
            ],
        ]);
    }

    /** GET /bloom/centres/{id} */
    public function show(int $id): JsonResponse
    {
        $facility = $this->baseQuery('all', false)->where('facilityId', $id)->first();

        if (!$facility) {
            return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Centre not found.'], 404);
        }

        $this->loadServices(collect([$facility]));
        $now = new \DateTimeImmutable('now', new \DateTimeZone((string) config('bloom.centres.timezone')));

        return response()->json(['status' => true, 'data' => CentrePresenter::toArray($facility, null, $now)]);
    }

    /** Active facilities, with coordinates unless $needCoords is false. */
    private function baseQuery(string $type, bool $needCoords = true)
    {
        $query = Facility::query()
            ->where('status', 'active')
            // isActive may be NULL on older rows; only an explicit false hides a centre.
            ->where(fn ($w) => $w->whereNull('isActive')->orWhere('isActive', true));

        if ($needCoords) {
            $query->whereNotNull('latitude')->whereNotNull('longitude')
                  ->where(fn ($w) => $w->where('latitude', '!=', 0)->orWhere('longitude', '!=', 0));
        }

        if ($type === 'screening') {
            $query->where('isScreeningCenter', true);
        } elseif ($type === 'treatment') {
            $query->where('isTreatmentCenter', true);
        } else {
            $query->where(fn ($w) => $w->where('isScreeningCenter', true)->orWhere('isTreatmentCenter', true));
        }

        return $query;
    }

    /** One extra query for all service names. A problem here must never break the list. */
    private function loadServices($facilities): void
    {
        if (!config('bloom.centres.include_services') || $facilities->isEmpty()) {
            return;
        }

        try {
            $facilities->load('services');
        } catch (\Throwable $e) {
            Log::warning('Bloom centres: could not load services', ['error' => $e->getMessage()]);
        }
    }
}

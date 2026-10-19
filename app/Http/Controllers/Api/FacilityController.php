<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FacilityController extends Controller
{
    /**
     * Get all facilities with stats
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $query = Facility::query();

        $visibleIds = $user->visibleFacilityIds();
        if ($visibleIds !== null) {
            $query->whereIn('facilityId', $visibleIds);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('facilityName', 'like', "%{$search}%")
                  ->orWhere('facilityCode', 'like', "%{$search}%")
                  ->orWhere('facilityState', 'like', "%{$search}%");
            });
        }

        // Filter by state
        if ($request->has('state') && $request->state !== 'all') {
            $query->where('facilityState', $request->state);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by facility type
        if ($request->has('facilityType') && $request->facilityType !== 'all') {
            switch ($request->facilityType) {
                case 'screening':
                    $query->where('isScreeningCenter', true);
                    break;
                case 'treatment':
                    $query->where('isTreatmentCenter', true);
                    break;
                case 'both':
                    $query->where('isScreeningCenter', true)
                          ->where('isTreatmentCenter', true);
                    break;
            }
        }

        $facilities = $query->get()->map(function ($facility) {
            return [
                'id' => $facility->facilityId,
                'facilityId' => $facility->facilityId,
                'facilityName' => $facility->facilityName,
                'facilityCode' => $facility->facilityCode,
                'facilityState' => $facility->facilityState,
                'facilityLga' => $facility->facilityLga,
                'facilityAddress' => $facility->facilityAddress,
                'clinicDays' => $facility->clinicDays,
                'clinicOpenTime' => $facility->clinicOpenTime,
                'clinicCloseTime' => $facility->clinicCloseTime,
                'clinicHoursDisplay' => $facility->formatClinicHours(),
                'phoneNumber' => $facility->phoneNumber,
                'email' => $facility->email,
                'status' => $facility->status,
                'facilityLevel' => $facility->facilityLevel,
                'facilityLevels' => $facility->facilityLevels ?? [],
                'parentFacilityId' => $facility->parentFacilityId,
                'stagesSupported' => $facility->stagesSupported,
                'latitude' => $facility->latitude,
                'longitude' => $facility->longitude,
                'isScreeningCenter' => $facility->isScreeningCenter,
                'isTreatmentCenter' => $facility->isTreatmentCenter,
                'facilityTypes' => $facility->facility_types,
                'facilityTypesArray' => $facility->facility_types_array,
                'activeUsers' => $facility->active_users_count,
                'totalScreenings' => $facility->total_screenings_count,
                'createdAt' => $facility->created_at,
                'updatedAt' => $facility->updated_at,
            ];
        });

        // Calculate stats — scoped the same way as the list above
        $statsBase = fn () => Facility::when($visibleIds !== null, fn ($q) => $q->whereIn('facilityId', $visibleIds));

        $stats = [
            'total' => $statsBase()->count(),
            'active' => $statsBase()->where('status', 'active')->count(),
            'inactive' => $statsBase()->where('status', 'inactive')->count(),
            'totalUsers' => \DB::table('users')->whereIn('facilityId', $statsBase()->pluck('facilityId'))->count(),
            'screeningCenters' => $statsBase()->where('isScreeningCenter', true)->count(),
            'treatmentCenters' => $statsBase()->where('isTreatmentCenter', true)->count(),
            'bothTypes' => $statsBase()->where('isScreeningCenter', true)->where('isTreatmentCenter', true)->count(),
        ];

        return response()->json([
            'status' => true,
            'facilities' => $facilities,
            'stats' => $stats,
        ]);
    }

    /**
     * Get a single facility
     */
    public function show(Facility $facility): JsonResponse
    {
        return response()->json([
            'status' => true,
            'facility' => [
                'id' => $facility->facilityId,
                'facilityName' => $facility->facilityName,
                'facilityCode' => $facility->facilityCode,
                'facilityState' => $facility->facilityState,
                'facilityLga' => $facility->facilityLga,
                'facilityAddress' => $facility->facilityAddress,
                'clinicDays' => $facility->clinicDays,
                'clinicOpenTime' => $facility->clinicOpenTime,
                'clinicCloseTime' => $facility->clinicCloseTime,
                'clinicHoursDisplay' => $facility->formatClinicHours(),
                'phoneNumber' => $facility->phoneNumber,
                'email' => $facility->email,
                'status' => $facility->status,
                'facilityLevel' => $facility->facilityLevel,
                'facilityLevels' => $facility->facilityLevels ?? [],
                'parentFacilityId' => $facility->parentFacilityId,
                'stagesSupported' => $facility->stagesSupported,
                'latitude' => $facility->latitude,
                'longitude' => $facility->longitude,
                'isScreeningCenter' => $facility->isScreeningCenter,
                'isTreatmentCenter' => $facility->isTreatmentCenter,
                'facilityTypes' => $facility->facility_types,
                'facilityTypesArray' => $facility->facility_types_array,
                'activeUsers' => $facility->active_users_count,
                'totalScreenings' => $facility->total_screenings_count,
                'createdAt' => $facility->created_at,
                'updatedAt' => $facility->updated_at,
            ],
        ]);
    }

    /**
     * Create a new facility
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'facilityName' => 'required|string|max:255',
            'facilityCode' => 'required|string|max:50|unique:facilities,facilityCode',
            'facilityState' => 'required|string|max:100',
            'facilityLga' => 'required|string|max:100',
            'facilityAddress' => 'required|string',
            'clinicDays' => 'nullable|array',
            'clinicDays.*' => 'string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'clinicOpenTime' => 'nullable|string',
            'clinicCloseTime' => 'nullable|string',
            'phoneNumber' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:facilities,email',
            'status' => 'sometimes|in:active,inactive',
            'parentFacilityId' => 'nullable|integer|exists:facilities,facilityId',
            'stagesSupported' => 'nullable|array',
            'stagesSupported.*' => 'string|in:stage2,stage3,stage4',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'isScreeningCenter' => 'sometimes|boolean',
            'isTreatmentCenter' => 'sometimes|boolean',
            'facilityLevels' => 'required|array|min:1',
            'facilityLevels.*' => 'string|in:feeder,subhub,hub|distinct',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate at least one type is selected
        $isScreening = $request->input('isScreeningCenter', true);
        $isTreatment = $request->input('isTreatmentCenter', false);

        if (!$isScreening && !$isTreatment) {
            return response()->json([
                'status' => false,
                'message' => 'Facility must be at least one type (Screening Center or Treatment Center)',
            ], 422);
        }

        // Only a facility with the Hub tier may also be a treatment center —
        // SubHubs and Feeders are screening-only in this referral hierarchy.
        $levels = array_values(array_unique($request->facilityLevels));
        $isHub = in_array('hub', $levels, true);

        if ($isTreatment && !$isHub) {
            return response()->json([
                'status' => false,
                'message' => 'Only a facility with the Hub tier can be a Treatment Center.',
            ], 422);
        }

        // Enforce the hierarchy shape: a Feeder's parent must be a SubHub,
        // a SubHub's parent must be a Hub. Hubs sit at the top and have
        // no parent.
        if ($request->filled('parentFacilityId')) {
            $parentError = $this->validateParentTier($levels, (int) $request->parentFacilityId);
            if ($parentError) {
                return response()->json(['status' => false, 'message' => $parentError], 422);
            }
        }

        $facility = Facility::create([
            'facilityName' => $request->facilityName,
            'facilityCode' => $request->facilityCode,
            'facilityState' => $request->facilityState,
            'facilityLga' => $request->facilityLga,
            'facilityAddress' => $request->facilityAddress,
            'clinicDays' => $request->clinicDays,
            'clinicOpenTime' => $request->clinicOpenTime,
            'clinicCloseTime' => $request->clinicCloseTime,
            'phoneNumber' => $request->phoneNumber,
            'email' => $request->email,
            'status' => $request->status ?? 'active',
            // Legacy single-level column, derived from the array so that
            // map(), index() and show() keep working.
            'facilityLevel' => Facility::highestLevel($levels),
            'facilityLevels' => $levels,
            'parentFacilityId' => $request->parentFacilityId,
            'stagesSupported' => $request->stagesSupported ?? ['stage2'],
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'isScreeningCenter' => $isScreening,
            'isTreatmentCenter' => $isTreatment,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Facility created successfully',
            'facility' => [
                'id' => $facility->facilityId,
                'facilityName' => $facility->facilityName,
                'facilityCode' => $facility->facilityCode,
                'facilityState' => $facility->facilityState,
                'facilityLga' => $facility->facilityLga,
                'facilityAddress' => $facility->facilityAddress,
                'clinicDays' => $facility->clinicDays,
                'clinicOpenTime' => $facility->clinicOpenTime,
                'clinicCloseTime' => $facility->clinicCloseTime,
                'clinicHoursDisplay' => $facility->formatClinicHours(),
                'phoneNumber' => $facility->phoneNumber,
                'email' => $facility->email,
                'status' => $facility->status,
                'facilityLevel' => $facility->facilityLevel,
                'facilityLevels' => $facility->facilityLevels ?? [],
                'parentFacilityId' => $facility->parentFacilityId,
                'stagesSupported' => $facility->stagesSupported,
                'latitude' => $facility->latitude,
                'longitude' => $facility->longitude,
                'isScreeningCenter' => $facility->isScreeningCenter,
                'isTreatmentCenter' => $facility->isTreatmentCenter,
                'facilityTypes' => $facility->facility_types,
            ],
        ], 201);
    }

    /**
     * Update a facility
     */
    public function update(Request $request, Facility $facility): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'facilityName' => 'sometimes|required|string|max:255',
            'facilityCode' => 'sometimes|required|string|max:50|unique:facilities,facilityCode,' . $facility->facilityId . ',facilityId',
            'facilityState' => 'sometimes|required|string|max:100',
            'facilityLga' => 'sometimes|required|string|max:100',
            'facilityAddress' => 'sometimes|required|string',
            'clinicDays' => 'nullable|array',
            'clinicDays.*' => 'string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'clinicOpenTime' => 'nullable|string',
            'clinicCloseTime' => 'nullable|string',
            'phoneNumber' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|max:255|unique:facilities,email,' . $facility->facilityId . ',facilityId',
            'status' => 'sometimes|in:active,inactive',
            'parentFacilityId' => 'nullable|integer|exists:facilities,facilityId|not_in:' . $facility->facilityId,
            'stagesSupported' => 'nullable|array',
            'stagesSupported.*' => 'string|in:stage2,stage3,stage4',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'isScreeningCenter' => 'sometimes|boolean',
            'isTreatmentCenter' => 'sometimes|boolean',
            'facilityLevels' => 'sometimes|required|array|min:1',
            'facilityLevels.*' => 'string|in:feeder,subhub,hub|distinct',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $levels = $request->has('facilityLevels')
            ? array_values(array_unique($request->facilityLevels))
            : ($facility->facilityLevels ?? []);
        $isHub = in_array('hub', $levels, true);

        // Validate type rules if types or tiers are being updated
        if ($request->hasAny(['isScreeningCenter', 'isTreatmentCenter', 'facilityLevels'])) {
            $isScreening = $request->input('isScreeningCenter', $facility->isScreeningCenter);
            $isTreatment = $request->input('isTreatmentCenter', $facility->isTreatmentCenter);

            if (!$isScreening && !$isTreatment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Facility must be at least one type (Screening Center or Treatment Center)',
                ], 422);
            }

            if ($isTreatment && !$isHub) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only a facility with the Hub tier can be a Treatment Center. Unset Treatment Center first, or keep the Hub tier.',
                ], 422);
            }
        }

        if ($request->filled('parentFacilityId')) {
            $parentError = $this->validateParentTier($levels, (int) $request->parentFacilityId);
            if ($parentError) {
                return response()->json(['status' => false, 'message' => $parentError], 422);
            }
        }

        $data = $request->only([
            'facilityName',
            'facilityCode',
            'facilityState',
            'facilityLga',
            'facilityAddress',
            'clinicDays',
            'clinicOpenTime',
            'clinicCloseTime',
            'phoneNumber',
            'email',
            'status',
            'facilityLevels',
            'parentFacilityId',
            'stagesSupported',
            'latitude',
            'longitude',
            'isScreeningCenter',
            'isTreatmentCenter',
        ]);

        // Keep the legacy single-level column in sync with the array.
        if ($request->has('facilityLevels')) {
            $data['facilityLevels'] = $levels;
            $data['facilityLevel'] = Facility::highestLevel($levels);
        }

        $facility->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Facility updated successfully',
            'facility' => [
                'id' => $facility->facilityId,
                'facilityName' => $facility->facilityName,
                'facilityCode' => $facility->facilityCode,
                'facilityState' => $facility->facilityState,
                'facilityLga' => $facility->facilityLga,
                'facilityAddress' => $facility->facilityAddress,
                'clinicDays' => $facility->clinicDays,
                'clinicOpenTime' => $facility->clinicOpenTime,
                'clinicCloseTime' => $facility->clinicCloseTime,
                'clinicHoursDisplay' => $facility->formatClinicHours(),
                'phoneNumber' => $facility->phoneNumber,
                'email' => $facility->email,
                'status' => $facility->status,
                'facilityLevel' => $facility->facilityLevel,
                'facilityLevels' => $facility->facilityLevels ?? [],
                'parentFacilityId' => $facility->parentFacilityId,
                'stagesSupported' => $facility->stagesSupported,
                'latitude' => $facility->latitude,
                'longitude' => $facility->longitude,
                'isScreeningCenter' => $facility->isScreeningCenter,
                'isTreatmentCenter' => $facility->isTreatmentCenter,
                'facilityTypes' => $facility->facility_types,
            ],
        ]);
    }

    /**
     * Enforces the referral hierarchy shape: a Feeder's parent must be a
     * SubHub, a SubHub's parent must be a Hub. A Hub sits at the top and
     * shouldn't have a parent at all.
     */
    protected function validateParentTier(array $levels, int $parentFacilityId): ?string
    {
        $top = Facility::highestLevel($levels);

        if ($top === 'hub') {
            return 'A facility with the Hub tier is top-level and cannot have a parent facility.';
        }

        $expected = $top === 'subhub' ? 'hub' : 'subhub';
        $parent = Facility::find($parentFacilityId);

        if (!$parent || !$parent->hasLevel($expected)) {
            return "A facility whose highest tier is " . ucfirst($top) . " must have a facility with the " . ucfirst($expected) . " tier as its parent.";
        }

        return null;
    }

    /**
     * Delete a facility
     */
    public function destroy(Facility $facility): JsonResponse
    {
        // Check if facility has users
        if ($facility->users()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete facility with active users. Please reassign users first.',
            ], 422);
        }

        $facility->delete();

        return response()->json([
            'status' => true,
            'message' => 'Facility deleted successfully',
        ]);
    }

    /**
     * Get list of states
     */
    public function states(): JsonResponse
    {
        $states = [
            "Abia", "Adamawa", "Akwa Ibom", "Anambra", "Bauchi", "Bayelsa",
            "Benue", "Borno", "Cross River", "Delta", "Ebonyi", "Edo",
            "Ekiti", "Enugu", "FCT", "Gombe", "Imo", "Jigawa", "Kaduna",
            "Kano", "Katsina", "Kebbi", "Kogi", "Kwara", "Lagos", "Nasarawa",
            "Niger", "Ogun", "Ondo", "Osun", "Oyo", "Plateau", "Rivers",
            "Sokoto", "Taraba", "Yobe", "Zamfara"
        ];

        return response()->json([
            'status' => true,
            'states' => $states,
        ]);
    }

    /**
     * Facility geo data for the national facility map — only facilities
     * with coordinates set are returned, since not every facility has
     * been geo-located yet. The frontend surfaces how many are missing
     * so it's visible as a data-quality gap, not silently dropped.
     */
    public function map(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $query = Facility::query();
        $visibleIds = $user->visibleFacilityIds();
        if ($visibleIds !== null) {
            $query->whereIn('facilityId', $visibleIds);
        }

        $totalCount = (clone $query)->count();

        $withCoords = (clone $query)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get([
                'facilityId', 'facilityName', 'facilityCode', 'facilityLevel', 'facilityLevels',
                'facilityState', 'facilityLga', 'latitude', 'longitude',
                'isScreeningCenter', 'isTreatmentCenter', 'stagesSupported', 'status',
            ]);

        return response()->json([
            'status' => true,
            'facilities' => $withCoords,
            'totalCount' => $totalCount,
            'missingCoordinatesCount' => $totalCount - $withCoords->count(),
        ]);
    }
}
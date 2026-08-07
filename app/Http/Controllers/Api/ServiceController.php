<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'services' => Service::where('isActive', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Facilities offering a given service — the actual list a booking
     * dropdown should populate from, keyed by service slug (stable,
     * code-referenceable) rather than a raw ID.
     */
    public function facilitiesForService(string $slug): JsonResponse
    {
        $service = Service::where('slug', $slug)->where('isActive', true)->firstOrFail();

        $facilities = $service->facilities()
            ->where('facilities.isActive', true)
            ->orderBy('facilityName')
            ->get(['facilities.facilityId', 'facilityName', 'facilityState', 'facilityLga', 'facilityLevel']);

        return response()->json(['facilities' => $facilities]);
    }
}
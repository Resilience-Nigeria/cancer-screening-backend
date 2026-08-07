<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
   public function store(Request $request, string $clientId): JsonResponse
{
    $data = $request->validate([
        'visitId'      => ['nullable', 'integer'],
        'serviceId'    => ['required_without:serviceSlug', 'integer', 'exists:services,serviceId'],
        'serviceSlug'  => ['required_without:serviceId', 'string', 'exists:services,slug'],
        'facilityId'   => ['required', 'integer', 'exists:facilities,facilityId'],
        'cancerType'   => ['nullable', 'string'],
        'bookingDate'  => ['required', 'date'],
        'notes'        => ['nullable', 'string'],
    ]);

    if (empty($data['serviceId'])) {
        $data['serviceId'] = Service::where('slug', $data['serviceSlug'])->value('serviceId');
    }
    unset($data['serviceSlug']);

    $booking = Booking::create([...$data, 'clientId' => $clientId, 'status' => 'pending']);

    return response()->json(['booking' => $booking->load(['service', 'facility'])], 201);
}
}
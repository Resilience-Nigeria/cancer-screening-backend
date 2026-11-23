<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportSetting;
use App\Support\OverdueResults;
use App\Support\ReportRecipients;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverdueResultsController extends Controller
{
    /**
     * GET /screening-samples/overdue?days=
     * Samples / tests still waiting for a result. By default: super / NICRAT admins see all facilities,
     * hospital admins their facilities, everyone else their own facility.
     * When "Apply to the in-app pop-up" is on in Report Settings, the pop-up follows the email list instead:
     * people not on the list see nothing, and each person sees the facilities their entry covers.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $setting = ReportSetting::for('overdue_results');
        $days = max(1, min((int) $request->query('days', $setting->option('days', OverdueResults::DEFAULT_DAYS)), 90));

        if ($setting->option('applyToPopup')) {
            $facilities = ReportRecipients::forUser('overdue_results', $user);
            if ($facilities === false) {
                return response()->json(['scope' => 'none', 'days' => $days, 'total' => 0, 'byFacility' => [], 'samples' => []]);
            }
            return response()->json(OverdueResults::forFacilities($facilities, null, $days));
        }

        return response()->json(OverdueResults::forUser($user, $days));
    }
}

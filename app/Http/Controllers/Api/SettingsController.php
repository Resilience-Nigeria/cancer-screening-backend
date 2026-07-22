<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can view system settings.'], 403);
        }

        $settings = Setting::orderBy('group')->orderBy('key')->get()->groupBy('group');

        return response()->json(['settings' => $settings]);
    }

    /**
     * Bulk-update settings. Accepts { settings: { key: value, ... } }
     * so the whole form can be saved in one request.
     */
    public function update(Request $request): JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can change system settings.'], 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if (!$setting) {
                continue; // Ignore unknown keys rather than creating arbitrary rows
            }

            // Validate select-type settings against their declared options
            if ($setting->type === 'select' && !empty($setting->options)) {
                if (!array_key_exists($value, $setting->options)) {
                    return response()->json([
                        'message' => "Invalid value for {$setting->label}: {$value}",
                    ], 422);
                }
            }

            $setting->update(['value' => (string) $value]);
            Cache::forget("setting:{$key}");
        }

        return response()->json(['message' => 'Settings updated.']);
    }
}

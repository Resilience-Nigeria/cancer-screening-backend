<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationProvider;
use App\Services\NotificationProviderTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationProviderController extends Controller
{
    protected function authorizeSuperAdmin(Request $request): ?JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can manage notification providers.'], 403);
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($error = $this->authorizeSuperAdmin($request)) {
            return $error;
        }

        return response()->json([
            'providers' => NotificationProvider::orderBy('channel')->get()->groupBy('channel'),
            'templates' => NotificationProviderTemplates::all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($error = $this->authorizeSuperAdmin($request)) {
            return $error;
        }

        $validated = $request->validate([
            'channel' => 'required|in:sms,email,whatsapp',
            'providerKey' => 'required|string',
            'providerName' => 'required|string',
            'config' => 'required|array',
        ]);

        $templates = NotificationProviderTemplates::all();
        if (!isset($templates[$validated['channel']][$validated['providerKey']])) {
            return response()->json(['message' => 'Unknown provider type for this channel.'], 422);
        }

        $exists = NotificationProvider::where('channel', $validated['channel'])
            ->where('providerKey', $validated['providerKey'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This provider is already configured for this channel.'], 422);
        }

        $provider = NotificationProvider::create([
            ...$validated,
            'isActive' => true,
            'isDefault' => false,
        ]);

        return response()->json(['message' => 'Provider added.', 'provider' => $provider], 201);
    }

    public function update(Request $request, NotificationProvider $provider): JsonResponse
    {
        if ($error = $this->authorizeSuperAdmin($request)) {
            return $error;
        }

        $validated = $request->validate([
            'providerName' => 'nullable|string',
            'config' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $provider->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['message' => 'Provider updated.', 'provider' => $provider]);
    }

    /**
     * Set this provider as the default for its channel — unsets any
     * other default on the same channel first, so exactly one stays
     * marked default per channel.
     */
    public function setDefault(Request $request, NotificationProvider $provider): JsonResponse
    {
        if ($error = $this->authorizeSuperAdmin($request)) {
            return $error;
        }

        if (!$provider->isActive) {
            return response()->json(['message' => 'Activate this provider before making it the default.'], 422);
        }

        NotificationProvider::where('channel', $provider->channel)->update(['isDefault' => false]);
        $provider->update(['isDefault' => true]);

        return response()->json(['message' => "{$provider->providerName} is now the default {$provider->channel} provider.", 'provider' => $provider]);
    }

    public function destroy(Request $request, NotificationProvider $provider): JsonResponse
    {
        if ($error = $this->authorizeSuperAdmin($request)) {
            return $error;
        }

        if ($provider->isDefault) {
            return response()->json(['message' => 'Cannot remove the default provider for a channel — set another provider as default first.'], 422);
        }

        $provider->delete();

        return response()->json(['message' => 'Provider removed.']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StaffPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Staff profile picture: upload / remove your own, and serve them (chat, staff directory, profile). */
class ProfilePhotoController extends Controller
{
    /** GET /mobile/profile/photo — my current picture. */
    public function show(): JsonResponse
    {
        return response()->json(['photoUrl' => StaffPhoto::url(auth('api')->user())]);
    }

    /** POST /mobile/profile/photo (multipart: photo) — JPG/PNG/WebP up to 5 MB. */
    public function store(Request $request): JsonResponse
    {
        /** @var User $me */
        $me = auth('api')->user();
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120']], [
            'photo.max' => 'The picture must be 5 MB or smaller.',
            'photo.mimes' => 'Use a JPG, PNG or WebP picture.',
        ]);
        $f = $request->file('photo');
        $ext = strtolower($f->getClientOriginalExtension() ?: $f->extension() ?: 'jpg');
        $path = $f->storeAs(StaffPhoto::DIR, Str::random(40) . '.' . $ext, StaffPhoto::DISK);
        $old = $me->photoPath;
        $me->forceFill(['photoPath' => $path])->save();
        if ($old && $old !== $path) Storage::disk(StaffPhoto::DISK)->delete($old);
        return response()->json(['message' => 'Profile picture updated.', 'photoUrl' => StaffPhoto::url($me)]);
    }

    /** DELETE /mobile/profile/photo */
    public function destroy(): JsonResponse
    {
        /** @var User $me */
        $me = auth('api')->user();
        if ($me->photoPath) Storage::disk(StaffPhoto::DISK)->delete($me->photoPath);
        $me->forceFill(['photoPath' => null])->save();
        return response()->json(['message' => 'Profile picture removed.', 'photoUrl' => null]);
    }

    /** GET /staff-photos/{file} — public (random file names), cached by phones for a week. */
    public function file(string $file)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}\.(jpe?g|png|webp|heic)$/i', $file), 404);
        $path = StaffPhoto::DIR . '/' . $file;
        abort_unless(Storage::disk(StaffPhoto::DISK)->exists($path), 404);
        return response()->file(Storage::disk(StaffPhoto::DISK)->path($path), ['Cache-Control' => 'public, max-age=604800, immutable']);
    }
}

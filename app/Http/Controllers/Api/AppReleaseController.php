<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * In-app updates for the NCSR Mobile App while it is distributed outside the Play Store.
 *  - Admins upload a new APK (or paste a link) on the web → Mobile App → App versions, and publish it.
 *  - Phones ask GET /mobile/app-version on start-up; if a newer build exists they offer to download and install it.
 *  - "Mandatory" releases make older phones update before they can carry on (once they're online).
 */
class AppReleaseController extends Controller
{
    private const DISK = 'local';
    private const DIR = 'apk';

    private function admin(): User
    {
        $u = auth('api')->user();
        abort_if(!$u || !($u->isSuperAdmin() || $u->isNicratStaff()), 403, 'Only a super admin or NICRAT admin can manage app versions.');
        return $u;
    }

    private function downloadUrl(AppRelease $r): ?string
    {
        if ($r->apkPath) return url("/api/mobile-app/download/{$r->id}/{$r->token}");
        return $r->apkUrl ? $this->directLink($r->apkUrl) : null;
    }

    /** A Google Drive "share" link becomes a direct-download link (works for files under ~100 MB). */
    private function directLink(string $url): string
    {
        if (preg_match('#drive\.google\.com/(?:file/d/|open\?id=|uc\?(?:.*&)?id=)([\w-]{20,})#', $url, $m)) {
            return "https://drive.usercontent.google.com/download?id={$m[1]}&export=download&confirm=t";
        }
        return $url;
    }

    private function row(AppRelease $r, array $phones = []): array
    {
        return [
            'id' => $r->id, 'version' => $r->version, 'versionCode' => (int) $r->versionCode, 'notes' => $r->notes,
            'source' => $r->apkPath ? 'upload' : 'link', 'apkUrl' => $r->apkUrl, 'downloadUrl' => $this->downloadUrl($r),
            'apkSize' => $r->apkSize ? (int) $r->apkSize : null, 'sha256' => $r->sha256,
            'mandatory' => (bool) $r->mandatory, 'published' => (bool) $r->published,
            'publishedAt' => $r->publishedAt?->toIso8601String(), 'createdAt' => $r->created_at?->toIso8601String(),
            'downloads' => (int) $r->downloads, 'phones' => (int) ($phones[$r->version] ?? 0),
        ];
    }

    // ── For the phone ────────────────────────────────────────────────────

    /**
     * GET /mobile/app-version?versionCode=4   (public — works on the sign-in screen too)
     * The newest published build, and the lowest build phones may still run (from mandatory releases).
     */
    public function latest(Request $request): JsonResponse
    {
        $latest = AppRelease::where('published', true)->orderByDesc('versionCode')->first();
        if (!$latest) return response()->json(['latest' => null, 'minVersionCode' => 0]);
        $min = (int) AppRelease::where('published', true)->where('mandatory', true)->max('versionCode');
        $mine = (int) $request->query('versionCode', 0);
        // Release notes for everything newer than this phone, newest first.
        $notes = AppRelease::where('published', true)->where('versionCode', '>', $mine)->orderByDesc('versionCode')->limit(5)
            ->get(['version', 'notes'])->filter(fn ($r) => trim((string) $r->notes) !== '')
            ->map(fn ($r) => ['version' => $r->version, 'notes' => $r->notes])->values();
        return response()->json([
            'latest' => [
                'version' => $latest->version, 'versionCode' => (int) $latest->versionCode, 'notes' => $latest->notes,
                'url' => $this->downloadUrl($latest), 'size' => $latest->apkSize ? (int) $latest->apkSize : null,
                'sha256' => $latest->sha256, 'publishedAt' => $latest->publishedAt?->toIso8601String(),
            ],
            'minVersionCode' => $min,
            'changes' => $notes,
        ]);
    }

    /** GET /mobile-app/download/{id}/{token} — public, the APK itself (the token makes the link unguessable). */
    public function download(int $id, string $token): StreamedResponse
    {
        $r = AppRelease::where('id', $id)->where('token', $token)->whereNotNull('apkPath')->firstOrFail();
        abort_if(!Storage::disk(self::DISK)->exists($r->apkPath), 404, 'The APK file is missing on the server.');
        AppRelease::where('id', $r->id)->increment('downloads');
        $name = "NCSR-Mobile-{$r->version}.apk";
        return Storage::disk(self::DISK)->download($r->apkPath, $name, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Length' => (string) Storage::disk(self::DISK)->size($r->apkPath),
        ]);
    }

    /** GET /mobile/app-latest — public: a plain link that always gets the newest APK (handy to share on WhatsApp). */
    public function latestApk()
    {
        $r = AppRelease::where('published', true)->orderByDesc('versionCode')->firstOrFail();
        $url = $this->downloadUrl($r);
        abort_if(!$url, 404);
        if (!$r->apkPath) AppRelease::where('id', $r->id)->increment('downloads');
        return redirect()->away($url);
    }

    // ── For admins (web → Mobile App → App versions) ─────────────────────

    /** GET /mobile-admin/releases */
    public function index(): JsonResponse
    {
        $this->admin();
        // Phones seen in the last 30 days, by the app version they run (from the security heartbeat).
        $phones = \Illuminate\Support\Facades\Schema::hasTable('staff_devices')
            ? DB::table('staff_devices')->whereNull('wipedAt')->where('lastSeenAt', '>=', now()->subDays(30))
                ->selectRaw('appVersion, COUNT(*) c')->groupBy('appVersion')->pluck('c', 'appVersion')->all()
            : [];
        $latest = AppRelease::where('published', true)->orderByDesc('versionCode')->first();
        $behind = $latest ? collect($phones)->filter(fn ($c, $v) => $v && version_compare((string) $v, $latest->version, '<'))->sum() : 0;
        return response()->json([
            'releases' => AppRelease::orderByDesc('versionCode')->get()->map(fn ($r) => $this->row($r, $phones))->values(),
            'phonesByVersion' => $phones,
            'phonesBehind' => (int) $behind,
            'latestLink' => url('/api/mobile/app-latest'),
            'maxUploadMb' => (int) floor(min($this->iniBytes('upload_max_filesize'), $this->iniBytes('post_max_size')) / 1048576),
        ]);
    }

    private function iniBytes(string $key): int
    {
        $v = trim((string) ini_get($key));
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }

    /**
     * POST /mobile-admin/releases  (multipart)
     * version, versionCode, notes?, mandatory?, publish?, and either apk (file) or apkUrl.
     */
    public function store(Request $request): JsonResponse
    {
        $me = $this->admin();
        $d = $request->validate([
            'version' => ['required', 'string', 'max:20', 'regex:/^\d+(\.\d+){0,3}$/'],
            'versionCode' => ['required', 'integer', 'min:1', 'unique:app_releases,versionCode'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'mandatory' => ['nullable', 'boolean'],
            'publish' => ['nullable', 'boolean'],
            'apk' => ['nullable', 'file', 'max:409600'], // 400 MB
            'apkUrl' => ['nullable', 'url', 'max:1000'],
        ], ['versionCode.unique' => 'A release with this build number already exists. Every new APK needs a higher android.versionCode.']);
        abort_if(!$request->hasFile('apk') && empty($d['apkUrl']), 422, 'Upload the APK file or paste a download link.');

        $path = $size = $sha = null;
        if ($request->hasFile('apk')) {
            $f = $request->file('apk');
            abort_if(strtolower($f->getClientOriginalExtension()) !== 'apk', 422, 'The file must be an .apk.');
            $path = $f->storeAs(self::DIR, "ncsr-mobile-{$d['version']}-{$d['versionCode']}-" . Str::random(6) . '.apk', self::DISK);
            $size = $f->getSize();
            $sha = hash_file('sha256', $f->getRealPath());
        }

        $r = AppRelease::create([
            'version' => $d['version'], 'versionCode' => $d['versionCode'], 'notes' => $d['notes'] ?? null,
            'apkPath' => $path, 'apkUrl' => $path ? null : $d['apkUrl'], 'apkSize' => $size, 'sha256' => $sha,
            'token' => Str::random(40), 'mandatory' => (bool) ($d['mandatory'] ?? false),
            'published' => (bool) ($d['publish'] ?? false), 'publishedAt' => ($d['publish'] ?? false) ? now() : null,
            'createdById' => $me->id,
        ]);
        $this->log('app_release_added', "Added NCSR Mobile App {$r->version} (build {$r->versionCode})" . ($r->published ? ' and published it.' : '.'));
        return response()->json(['message' => $r->published ? "Version {$r->version} published — phones will offer the update." : "Version {$r->version} saved (not published yet).", 'release' => $this->row($r)], 201);
    }

    /** PATCH /mobile-admin/releases/{id}  { notes?, mandatory?, published? } */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->admin();
        $r = AppRelease::findOrFail($id);
        $d = $request->validate(['notes' => ['nullable', 'string', 'max:3000'], 'mandatory' => ['nullable', 'boolean'], 'published' => ['nullable', 'boolean']]);
        if (array_key_exists('notes', $d)) $r->notes = $d['notes'];
        if (array_key_exists('mandatory', $d) && $d['mandatory'] !== null) $r->mandatory = $d['mandatory'];
        if (array_key_exists('published', $d) && $d['published'] !== null) {
            if ($d['published'] && !$r->published) $r->publishedAt = now();
            $r->published = $d['published'];
        }
        $r->save();
        $this->log('app_release_updated', "Updated NCSR Mobile App {$r->version}: " . ($r->published ? 'published' : 'unpublished') . ($r->mandatory ? ', mandatory' : '') . '.');
        return response()->json(['message' => $r->published ? "Version {$r->version} is live." : "Version {$r->version} is hidden from phones.", 'release' => $this->row($r)]);
    }

    /** DELETE /mobile-admin/releases/{id} — removes the release and its uploaded APK. */
    public function destroy(int $id): JsonResponse
    {
        $this->admin();
        $r = AppRelease::findOrFail($id);
        if ($r->apkPath) Storage::disk(self::DISK)->delete($r->apkPath);
        $r->delete();
        $this->log('app_release_deleted', "Deleted NCSR Mobile App {$r->version} (build {$r->versionCode}).");
        return response()->json(['message' => "Version {$r->version} deleted."]);
    }

    private function log(string $action, string $text): void
    {
        if (class_exists(ActivityLog::class)) {
            try {
                ActivityLog::record($action, $text, ['subjectType' => 'app_release']);
            } catch (\Throwable $e) {
            }
        }
    }
}

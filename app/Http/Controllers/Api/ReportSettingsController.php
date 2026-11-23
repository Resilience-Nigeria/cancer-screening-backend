<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ReportRecipient;
use App\Models\ReportSetting;
use App\Models\User;
use App\Support\OverdueResults;
use App\Support\ReportMailer;
use App\Support\ReportRecipients;
use App\Support\WeeklySummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Report Settings page: when each scheduled email goes out and who gets it.
 * Super Admins and NICRAT Admins only.
 */
class ReportSettingsController extends Controller
{
    private function admin()
    {
        $user = auth('api')->user();
        abort_unless($user && ($user->isSuperAdmin() || $user->isNicratStaff()), 403, 'Only Super Admins and NICRAT Admins can change report settings.');
        return $user;
    }

    private function key(string $key): string
    {
        abort_unless(array_key_exists($key, ReportRecipients::REPORTS), 404, 'Unknown report.');
        return $key;
    }

    private function log(string $text, array $details = []): void
    {
        if (class_exists(ActivityLog::class)) {
            ActivityLog::record('report_settings_changed', $text, ['details' => $details]);
        }
    }

    private function recipientRow(ReportRecipient $r, $names, $users): array
    {
        $u = $r->userId ? $users->get((int) $r->userId) : null;
        return [
            'id' => $r->id,
            'type' => $r->type,
            'roleName' => $r->roleName,
            'roleLabel' => $r->roleName ? ReportRecipients::roleLabel($r->roleName) : null,
            'userId' => $r->userId,
            'userName' => $u ? (trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email) : null,
            'userEmail' => $u->email ?? null,
            'email' => $r->email,
            'name' => $r->name,
            'scope' => $r->scope,
            'facilityIds' => $r->facilityIds ?? [],
            'facilityNames' => collect($r->facilityIds ?? [])->map(fn ($id) => $names[$id] ?? "#{$id}")->values(),
            'isExcluded' => $r->isExcluded,
            'active' => $r->active,
        ];
    }

    private function settingRow(ReportSetting $s): array
    {
        $next = $s->nextRunAt();
        return [
            'reportKey' => $s->reportKey,
            'title' => ReportRecipients::REPORTS[$s->reportKey] ?? $s->reportKey,
            'enabled' => $s->enabled,
            'frequency' => $s->frequency,
            'dayOfWeek' => $s->dayOfWeek,
            'sendTime' => $s->sendTime,
            'options' => (object) ($s->options ?? []),
            'nextRunAt' => $next ? $next->format('D j M Y, H:i') : null,
            'lastRunAt' => $s->lastRunAt ? $s->lastRunAt->timezone(ReportSetting::TIMEZONE)->format('D j M Y, H:i') : null,
        ];
    }

    /** GET /report-settings */
    public function index(): JsonResponse
    {
        $this->admin();
        $names = DB::table('facilities')->pluck('facilityName', 'facilityId');
        $users = User::all()->keyBy('id');
        $rows = ReportRecipient::orderBy('type')->orderBy('id')->get()->groupBy('reportKey');

        $reports = [];
        foreach (array_keys(ReportRecipients::REPORTS) as $key) {
            $reports[] = $this->settingRow(ReportSetting::for($key)) + [
                'recipients' => ($rows[$key] ?? collect())->map(fn ($r) => $this->recipientRow($r, $names, $users))->values(),
            ];
        }

        return response()->json([
            'reports' => $reports,
            'roles' => DB::table('roles')
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('roles', 'isStaffRole'), fn ($q) => $q->where(fn ($w) => $w->whereNull('isStaffRole')->orWhere('isStaffRole', true)))
                ->orderBy('roleName')->get(['roleName', 'roleDescription'])
                ->map(fn ($r) => ['roleName' => $r->roleName, 'label' => ReportRecipients::roleLabel($r->roleName), 'description' => $r->roleDescription ?? null]),
            'facilities' => DB::table('facilities')->orderBy('facilityName')->get(['facilityId', 'facilityName', 'facilityState']),
            'timezone' => ReportSetting::TIMEZONE,
        ]);
    }

    /** PUT /report-settings/{key} — on/off, frequency, day, time, options */
    public function update(Request $request, string $key): JsonResponse
    {
        $user = $this->admin();
        $key = $this->key($key);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'frequency' => ['required', Rule::in($key === 'weekly_summary' ? ['weekly'] : ['daily', 'weekdays', 'weekly'])],
            'dayOfWeek' => ['nullable', 'integer', 'between:0,6', Rule::requiredIf(fn () => $request->input('frequency') === 'weekly')],
            'sendTime' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'options' => ['nullable', 'array'],
            'options.days' => ['nullable', 'integer', 'between:1,90'],
            'options.applyToPopup' => ['nullable', 'boolean'],
        ]);

        $s = ReportSetting::for($key);
        $before = $s->only(['enabled', 'frequency', 'dayOfWeek', 'sendTime', 'options']);
        $s->fill([
            'enabled' => $data['enabled'],
            'frequency' => $data['frequency'],
            'dayOfWeek' => $data['frequency'] === 'weekly' ? $data['dayOfWeek'] : null,
            'sendTime' => $data['sendTime'],
            'options' => array_merge($s->options ?? [], $data['options'] ?? []),
            'updatedBy' => $user->id,
        ]);
        // Already sent today? Moving the time later must not send it a second time today.
        if (($s->isDirty('sendTime') || $s->isDirty('dayOfWeek') || $s->isDirty('frequency')) && $s->lastRunAt
            && $s->lastRunAt->copy()->timezone(ReportSetting::TIMEZONE)->isSameDay(now(ReportSetting::TIMEZONE))) {
            $at = $s->dueAtToday();
            if ($at) $s->lastRunAt = $at->copy()->timezone(config('app.timezone'));
        }
        $s->save();

        $this->log('Changed the schedule of the ' . strtolower(ReportRecipients::REPORTS[$key]) . '.', ['report' => $key, 'before' => $before, 'after' => $s->only(['enabled', 'frequency', 'dayOfWeek', 'sendTime', 'options'])]);
        return response()->json(['message' => 'Saved.', 'report' => $this->settingRow($s)]);
    }

    /** POST /report-settings/{key}/recipients */
    public function addRecipient(Request $request, string $key): JsonResponse
    {
        $user = $this->admin();
        $key = $this->key($key);
        $data = $request->validate([
            'type' => ['required', Rule::in(['role', 'user', 'email'])],
            'roleName' => ['required_if:type,role', 'nullable', 'string', 'exists:roles,roleName'],
            'userId' => ['required_if:type,user', 'nullable', 'integer', 'exists:users,id'],
            'email' => ['required_if:type,email', 'nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', Rule::in(['own', 'all', 'facilities'])],
            'facilityIds' => ['required_if:scope,facilities', 'nullable', 'array'],
            'facilityIds.*' => ['integer'],
            'isExcluded' => ['nullable', 'boolean'],
        ]);
        if ($data['type'] === 'email' && $data['scope'] === 'own') {
            return response()->json(['message' => 'An email address has no facility of its own. Choose "All facilities" or pick facilities.'], 422);
        }

        // One row per role / person / address per report: update it instead of adding a duplicate.
        $match = ReportRecipient::where('reportKey', $key)->where('type', $data['type'])
            ->when($data['type'] === 'role', fn ($q) => $q->where('roleName', $data['roleName']))
            ->when($data['type'] === 'user', fn ($q) => $q->where('userId', $data['userId']))
            ->when($data['type'] === 'email', fn ($q) => $q->where('email', strtolower(trim($data['email']))))
            ->first();

        $row = $match ?? new ReportRecipient(['reportKey' => $key, 'type' => $data['type'], 'createdBy' => $user->id]);
        $row->fill([
            'roleName' => $data['type'] === 'role' ? $data['roleName'] : null,
            'userId' => $data['type'] === 'user' ? $data['userId'] : null,
            'email' => $data['type'] === 'email' ? strtolower(trim($data['email'])) : null,
            'name' => $data['type'] === 'email' ? ($data['name'] ?? null) : null,
            'scope' => $data['scope'],
            'facilityIds' => $data['scope'] === 'facilities' ? array_values(array_unique($data['facilityIds'] ?? [])) : null,
            'isExcluded' => $data['type'] === 'user' && !empty($data['isExcluded']),
            'active' => true,
        ])->save();

        $who = $row->roleName ? 'role ' . ReportRecipients::roleLabel($row->roleName) : ($row->email ?: (User::find($row->userId)->email ?? "user #{$row->userId}"));
        $this->log(($row->isExcluded ? 'Excluded ' : 'Added ') . "{$who} " . ($row->isExcluded ? 'from' : 'to') . ' the ' . strtolower(ReportRecipients::REPORTS[$key]) . '.', ['report' => $key, 'recipientId' => $row->id]);

        return response()->json(['message' => $match ? 'Updated.' : 'Added.', 'id' => $row->id], $match ? 200 : 201);
    }

    /** PATCH /report-settings/recipients/{id} — scope, facilities, active, excluded */
    public function updateRecipient(Request $request, int $id): JsonResponse
    {
        $this->admin();
        $row = ReportRecipient::findOrFail($id);
        $data = $request->validate([
            'scope' => ['sometimes', Rule::in(['own', 'all', 'facilities'])],
            'facilityIds' => ['nullable', 'array'],
            'facilityIds.*' => ['integer'],
            'active' => ['sometimes', 'boolean'],
            'isExcluded' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        $scope = $data['scope'] ?? $row->scope;
        if ($row->type === 'email' && $scope === 'own') {
            return response()->json(['message' => 'An email address has no facility of its own. Choose "All facilities" or pick facilities.'], 422);
        }
        if ($scope === 'facilities' && empty($data['facilityIds'] ?? $row->facilityIds)) {
            return response()->json(['message' => 'Pick at least one facility.'], 422);
        }
        $row->fill([
            'scope' => $scope,
            'facilityIds' => $scope === 'facilities' ? array_values(array_unique($data['facilityIds'] ?? $row->facilityIds ?? [])) : null,
            'active' => $data['active'] ?? $row->active,
            'isExcluded' => $row->type === 'user' ? ($data['isExcluded'] ?? $row->isExcluded) : false,
            'name' => $row->type === 'email' && array_key_exists('name', $data) ? $data['name'] : $row->name,
        ])->save();

        $this->log('Changed a recipient of the ' . strtolower(ReportRecipients::REPORTS[$row->reportKey] ?? $row->reportKey) . '.', ['report' => $row->reportKey, 'recipientId' => $row->id]);
        return response()->json(['message' => 'Saved.']);
    }

    /** DELETE /report-settings/recipients/{id} */
    public function removeRecipient(int $id): JsonResponse
    {
        $this->admin();
        $row = ReportRecipient::findOrFail($id);
        $key = $row->reportKey;
        $row->delete();
        $this->log('Removed a recipient from the ' . strtolower(ReportRecipients::REPORTS[$key] ?? $key) . '.', ['report' => $key, 'recipientId' => $id]);
        return response()->json(['message' => 'Removed.']);
    }

    /** GET /report-settings/{key}/audience — everyone who will actually get it, and what they see */
    public function audience(string $key): JsonResponse
    {
        $this->admin();
        $key = $this->key($key);
        $names = DB::table('facilities')->pluck('facilityName', 'facilityId');
        $days = (int) ReportSetting::for($key)->option('days', OverdueResults::DEFAULT_DAYS);
        $counts = [];

        $people = collect(ReportRecipients::resolve($key))->map(function ($r) use ($key, $names, $days, &$counts) {
            $row = [
                'email' => $r['email'],
                'name' => $r['name'],
                'role' => $r['user']?->user_role?->roleName ? ReportRecipients::roleLabel($r['user']->user_role->roleName) : null,
                'scopeText' => ReportRecipients::scopeText($r['facilities'], $names),
                'via' => array_values(array_unique($r['via'])),
            ];
            if ($key === 'overdue_results') {
                $k = $r['facilities'] === null ? 'all' : implode(',', collect($r['facilities'])->sort()->all());
                $counts[$k] ??= OverdueResults::forFacilities($r['facilities'], null, $days, 1)['total'];
                $row['overdueNow'] = $counts[$k];
            }
            return $row;
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return response()->json(['reportKey' => $key, 'total' => $people->count(), 'people' => $people]);
    }

    /** GET /report-settings/users?search= — staff to add by name */
    public function users(Request $request): JsonResponse
    {
        $this->admin();
        $q = trim((string) $request->query('search', ''));
        $users = User::with(['user_role', 'facility'])
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('firstName', 'like', "%{$q}%")
                ->orWhere('lastName', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhereRaw("CONCAT(COALESCE(firstName,''),' ',COALESCE(lastName,'')) like ?", ["%{$q}%"])))
            ->whereNotNull('email')
            ->orderBy('firstName')
            ->limit(20)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email,
                'email' => $u->email,
                'role' => $u->user_role?->roleName ? ReportRecipients::roleLabel($u->user_role->roleName) : null,
                'facility' => $u->facility->facilityName ?? null,
                'status' => $u->status ?? null,
            ]);
        return response()->json(['users' => $users]);
    }

    /** GET /report-settings/weekly_summary/preview?facilityId= — the email as HTML (all facilities by default) */
    public function preview(Request $request, string $key): JsonResponse
    {
        $user = $this->admin();
        $key = $this->key($key);
        [$html, $subject] = $this->render($key, $user, $request->filled('facilityId') ? [(int) $request->facilityId] : null);
        return $html === null
            ? response()->json(['subject' => null, 'html' => null, 'message' => $subject])
            : response()->json(['subject' => $subject, 'html' => $html]);
    }

    /** POST /report-settings/{key}/send-test — send the report (all facilities) to the signed-in admin now */
    public function sendTest(string $key): JsonResponse
    {
        $user = $this->admin();
        $key = $this->key($key);
        [$html, $subject] = $this->render($key, $user, null);
        if ($html === null) {
            return response()->json(['message' => $subject], 422);
        }
        $name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: $user->email;
        $res = ReportMailer::send($user->email, $name, '[Test] ' . $subject, $html);
        if ($res !== true) {
            return response()->json(['message' => "Could not send: {$res}"], 502);
        }
        return response()->json(['message' => "Test email sent to {$user->email}."]);
    }

    /** @return array{0: ?string, 1: string} [html, subject] — html null with a message when there's nothing to send */
    private function render(string $key, $user, ?array $facilities): array
    {
        $name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')) ?: $user->email;
        $names = DB::table('facilities')->pluck('facilityName', 'facilityId');

        if ($key === 'weekly_summary') {
            [$from, $to] = WeeklySummary::defaultPeriod();
            $summary = WeeklySummary::build($facilities, $from, $to);
            return [view('emails.weekly-summary', [
                'recipientName' => $name,
                'summary' => $summary,
                'scopeText' => ReportRecipients::scopeText($facilities, $names),
                'link' => ReportMailer::frontendUrl('/ncsr/analytics'),
            ])->render(), "NCSR weekly summary · {$summary['period']['label']}"];
        }

        $days = (int) ReportSetting::for($key)->option('days', OverdueResults::DEFAULT_DAYS);
        $data = OverdueResults::forFacilities($facilities, null, $days);
        if ($data['total'] === 0) {
            return [null, "No results are overdue (more than {$days} days) right now, so there is nothing to send."];
        }
        $link = ReportMailer::frontendUrl('/ncsr/screening-tests-page?tab=results');
        $subject = (new \App\Mail\OverdueResultsReminder($name, $data, $link))->envelope()->subject;
        return [view('emails.overdue-results', [
            'recipientName' => $name, 'data' => $data, 'link' => $link, 'logoBase' => rtrim((string) config('app.url'), '/'),
        ])->render(), $subject];
    }
}

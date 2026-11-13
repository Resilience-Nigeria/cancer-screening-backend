<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomNavigatorSignup;
use App\Models\Facility;
use App\Models\User;
use App\Services\Bloom\BloomCaseService;
use App\Support\Bloom\NigerianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Public endpoints for community health workers: pick a facility, sign up, check approval.
 * Sign-in itself is the normal NCSR POST /api/auth/login, so the same account also works on the web app.
 */
class NavigatorAuthController extends Controller
{
    /** Professions offered in the app's sign-up form (keep in sync with src/lib/chw/professions.ts). */
    public const PROFESSIONS = [
        'Community Health Extension Worker (CHEW)',
        'Community Health Officer (CHO)',
        'Doctor',
        'Nurse',
        'Midwife',
        'Medical Laboratory Scientist',
        'Pharmacist',
        'Radiographer',
        'Health Records Officer',
        'Health Educator',
        'Other health worker',
    ];

    /** GET /bloom-chw/facilities?search=  All active facilities (names and places only) for the sign-up drop-down. */
    public function facilities(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('search', ''));

        $rows = Facility::where('status', 'active')
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->where('facilityName', 'like', "%{$term}%")
                      ->orWhere('facilityLga', 'like', "%{$term}%")
                      ->orWhere('facilityState', 'like', "%{$term}%");
                });
            })
            ->orderBy('facilityName')->limit(2000)
            ->get(['facilityId', 'facilityName', 'facilityLga', 'facilityState']);

        return response()->json(['status' => true, 'data' => $rows->map(fn ($f) => [
            'id'    => (int) $f->facilityId,
            'name'  => $f->facilityName,
            'lga'   => $f->facilityLga,
            'state' => $f->facilityState,
        ])->values()]);
    }

    /** POST /bloom-chw/register */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firstName'  => ['required', 'string', 'max:60'],
            'lastName'   => ['required', 'string', 'max:60'],
            'email'      => ['required', 'email', 'max:150'],
            'phone'      => ['required', 'string', 'max:20'],
            'facilityId' => ['required', 'integer'],
            'password'   => ['required', 'string', 'min:8', 'max:100'],
            'profession' => ['required', 'string', 'in:' . implode(',', array_map(fn ($p) => str_replace(',', '', $p), self::PROFESSIONS))],
            'staffId'    => ['required', 'string', 'max:50'],
            'note'       => ['nullable', 'string', 'max:300'],
        ], [
            'profession.in' => 'Choose your profession from the list.',
            'staffId.required' => 'Enter your staff ID.',
        ]);

        $phone = NigerianPhone::normalize($data['phone']);
        if (!$phone) {
            return $this->fail('INVALID_PHONE', 'Enter a valid Nigerian phone number.', 422);
        }
        $local = NigerianPhone::local($phone);
        $email = mb_strtolower(trim($data['email']));

        if (User::where('email', $email)->exists()) {
            return $this->fail('EMAIL_TAKEN', 'An NCSR account already uses this email. Sign in instead, or use another email.', 422);
        }
        if (User::whereIn('phoneNumber', [$local, $phone, '+' . $phone])->exists()) {
            return $this->fail('PHONE_TAKEN', 'An NCSR account already uses this phone number.', 422);
        }

        $facility = Facility::where('facilityId', $data['facilityId'])->where('status', 'active')->first();
        if (!$facility) {
            return $this->fail('FACILITY_NOT_FOUND', 'Choose your facility from the list.', 422);
        }

        $roleId = BloomCaseService::navigatorRoleId();
        if (!$roleId) {
            return $this->fail('ROLE_MISSING', 'Health worker sign-up is not available yet. Please contact NICRAT.', 503);
        }

        DB::transaction(function () use ($data, $email, $local, $facility, $roleId) {
            // A normal NCSR user with the NAVIGATOR role, kept inactive until an admin verifies them.
            $user = User::create([
                'firstName'   => trim($data['firstName']),
                'lastName'    => trim($data['lastName']),
                'email'       => $email,
                'phoneNumber' => $local,
                'password'    => Hash::make($data['password']),
                'role'        => $roleId,
                'status'      => 'inactive',
                'facilityId'  => $facility->facilityId,
            ]);

            BloomNavigatorSignup::create([
                'user_id'        => $user->id,
                'facility_id'    => $facility->facilityId,
                'status'         => 'pending',
                'profession'     => $data['profession'],
                'staff_id'       => trim($data['staffId']),
                'applicant_note' => isset($data['note']) ? trim($data['note']) : null,
            ]);
        });

        return response()->json([
            'status'  => true,
            'message' => 'Thanks. Your account will be checked before you can see cases. We will email you when it is approved.',
        ], 201);
    }

    /** POST /bloom-chw/account-status  { login, password }  — tells a health worker whether they are approved yet. */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:150'], 'password' => ['required', 'string', 'max:100']]);
        $login = trim($data['login']);

        $q = User::query();
        if (str_contains($login, '@')) {
            $q->where('email', mb_strtolower($login));
        } else {
            $phone = NigerianPhone::normalize($login);
            $q->whereIn('phoneNumber', $phone ? [NigerianPhone::local($phone), $phone, '+' . $phone] : [$login]);
        }
        $user = $q->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->fail('INVALID_CREDENTIALS', 'Email/phone or password is not correct.', 401);
        }

        $signup = BloomNavigatorSignup::where('user_id', $user->id)->first();

        return response()->json([
            'status' => true,
            'data'   => [
                'review'      => $signup->status ?? ($user->status === 'active' ? 'approved' : 'inactive'),
                'note'        => $signup && $signup->status === 'rejected' ? $signup->review_note : null,
                'accountActive' => $user->status === 'active',
            ],
        ]);
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['status' => false, 'code' => $code, 'message' => $message], $status);
    }
}

<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomAppointmentRequest;
use App\Models\BloomOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use Concerns;

    /** PATCH /bloom/profile  — name, age and what the person came for. Phone is not editable. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:80'],
            'age'  => ['sometimes', 'nullable', 'integer', 'between:10,110'],
            'need' => ['sometimes', 'nullable', 'string', Rule::in(self::NEEDS)],
        ]);

        $account = $this->account();
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }
        $account->fill($data)->save();

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated.',
            'user'    => $this->accountPayload($account->fresh()),
        ]);
    }

    /** POST /bloom/profile/password */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => array_merge($this->passwordRules(), ['different:current_password']),
        ]);

        $account = $this->account();

        if (!Hash::check($data['current_password'], $account->password)) {
            throw ValidationException::withMessages(['current_password' => ['Your current password is not right.']]);
        }

        $account->forceFill(['password' => Hash::make($data['password'])])->save();

        return response()->json(['status' => true, 'message' => 'Password changed.']);
    }

    /** DELETE /bloom/account  — permanently removes the account and its requests. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        $account = $this->account();

        if (!Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages(['password' => ['That password is not right.']]);
        }

        DB::transaction(function () use ($account) {
            BloomAppointmentRequest::where('account_id', $account->id)->delete();
            BloomOtp::where('phone', $account->phone)->delete();
            $account->delete();
        });

        try {
            Auth::guard('bloom')->logout();
        } catch (\Throwable $e) {
            // The account is already gone; a failed blacklist write does not matter.
        }

        return response()->json(['status' => true, 'message' => 'Your account has been deleted.']);
    }
}

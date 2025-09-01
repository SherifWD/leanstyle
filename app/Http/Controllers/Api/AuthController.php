<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Traits\backendTraits;
use App\Traits\HelpersTrait;
use Illuminate\Support\Facades\Cache;
class AuthController extends Controller
{
    use backendTraits, HelpersTrait;

    /**
     * POST /api/auth/register
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required','string','max:190'],
            'phone'    => ['required','string','max:30','unique:users,phone'],
            'password' => ['required','string','min:8'],
            'role'     => ['nullable', Rule::in(['shop_owner','delivery_boy','admin'])],
        ]);

        $user = new User();
        $user->name     = $data['name'];
        $user->phone    = $data['phone'];
        $user->password = Hash::make($data['password']);
        $user->role     = $data['role'] ?? 'shop_owner';
        $user->save();

        $token = JWTAuth::fromUser($user);

        return $this->returnData('auth', [
            'user'  => $this->userPayload($user),
            'token' => [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => auth('api')->factory()->getTTL() * 60,
            ],
        ], 'Registration successful');
    }

    /**
     * POST /api/auth/login
     * Accepts phone OR email.
     */
   public function login(Request $request)
{
    $credentials = $request->validate([
        'login'    => ['required','string'],
        'password' => ['required','string'],
        'role'     => ['nullable', Rule::in(['customer','shop_owner','delivery_boy','admin'])],
    ]);

    $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    $user  = User::where($field, $credentials['login'])->first();

    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        return $this->returnError('Invalid credentials', 422);
    }
    if (!empty($credentials['role']) && $user->role !== $credentials['role']) {
        return $this->returnError('Role mismatch for this account', 403);
    }
    if ($user->is_blocked) {
        return $this->returnError('Account is blocked', 403);
    }

    $token = JWTAuth::fromUser($user);
    return $this->returnData('auth', [
        'user'  => $this->userPayload($user),
        'token' => [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ],
    ], 'Login successful');
}


    /**
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        return $this->returnData('user', $this->userPayload($request->user('api')), 'Authenticated user data');
    }

    /**
     * POST /api/auth/logout
     */
    public function logout()
    {
        try {
            auth('api')->logout();
        } catch (JWTException $e) {
            // token might already be invalid; ignore
        }
        return $this->returnSuccessMessage('Logged out successfully');
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        return $this->returnData('token', [
            'access_token' => auth('api')->refresh(),
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ], 'Token refreshed');
    }

    /**
     * POST /api/auth/update-password
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user('api');

        $data = $request->validate([
            'current_password' => ['required','string'],
            'new_password'     => ['required','string','min:8'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return $this->returnError('Current password is incorrect', 422);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return $this->returnSuccessMessage('Password updated successfully');
    }

    private function userPayload(?User $user): array
    {
        if (!$user) return [];

        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'phone'        => $user->phone,
            'email'        => $user->email,
            'role'         => $user->role,
            'store_id'     => $user->store_id,
            'is_blocked'   => (bool)$user->is_blocked,
            'is_available' => (bool)$user->is_available,
        ];
    }

    public function forgotRequest(Request $request)
{
    $data = $request->validate(['phone' => ['required','string','max:30']]);

    $user = User::where('phone', $data['phone'])->first();
    if (!$user) return $this->returnError('Phone not found', 404);

    $otp = (string) random_int(100000, 999999);
    Cache::put("otp:{$data['phone']}", $otp, now()->addMinutes(10));

    // TODO: send via SMS gateway
    \Log::info("DEBUG OTP for {$data['phone']}: {$otp}");

    return $this->returnSuccessMessage('OTP sent to your phone');
}

/**
 * POST /api/auth/forgot/verify
 * { phone, otp }
 */
public function forgotVerify(Request $request)
{
    $data = $request->validate([
        'phone' => ['required','string','max:30'],
        'otp'   => ['required','string','size:6'],
    ]);

    $saved = Cache::get("otp:{$data['phone']}");
    if (!$saved || $saved !== $data['otp']) {
        return $this->returnError('Invalid or expired OTP', 422);
    }
    return $this->returnSuccessMessage('OTP verified');
}

/**
 * POST /api/auth/forgot/reset
 * { phone, otp, new_password }
 */
public function forgotReset(Request $request)
{
    $data = $request->validate([
        'phone'        => ['required','string','max:30'],
        'otp'          => ['required','string','size:6'],
        'new_password' => ['required','string','min:8'],
    ]);

    $saved = Cache::get("otp:{$data['phone']}");
    if (!$saved || $saved !== $data['otp']) {
        return $this->returnError('Invalid or expired OTP', 422);
    }

    $user = User::where('phone', $data['phone'])->first();
    if (!$user) return $this->returnError('Phone not found', 404);

    $user->password = Hash::make($data['new_password']);
    $user->save();
    Cache::forget("otp:{$data['phone']}");

    return $this->returnSuccessMessage('Password reset successfully');
}

}

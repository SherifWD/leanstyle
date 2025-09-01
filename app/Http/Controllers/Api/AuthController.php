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
            'login'    => ['required','string'], // phone or email
            'password' => ['required','string'],
        ]);

        // Resolve by login field
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $user = User::where($field, $credentials['login'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return $this->returnError('Invalid credentials', 422);
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
}

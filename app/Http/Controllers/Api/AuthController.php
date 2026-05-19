<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    /** POST /api/auth/login */

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'full_name' => $data['full_name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'password'  => Hash::make($data['password']),
        ]);

        return $this->created($user, 'Registration successful. Please log in.');
    }
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        if (!$user->is_active) {
            return $this->error('Account is deactivated. Contact administrator.', 403);
        }

        $user->tokens()->delete(); // single-session security

        $token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;

        return $this->success([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'full_name' => $user->full_name,
                'email'     => $user->email,
                'phone'     => $user->phone,
                'is_active' => $user->is_active,
            ],
        ], 'Login successful');
    }

    /** POST /api/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return $this->success(null, 'Logged out successfully');
    }

    /** GET /api/auth/me */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role', 'managedRigs:id,name,code');

        return $this->success([
            'id'           => $user->id,
            'full_name'    => $user->full_name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'is_active'    => $user->is_active,
            'managed_rigs' => $user->managedRigs,
            'created_at'   => $user->created_at?->format('Y-m-d'),
        ]);
    }

    /** PUT /api/auth/profile */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());
        return $this->success($request->user(), 'Profile updated');
    }

    /** PUT /api/auth/password */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return $this->success(null, 'Password changed. Please log in again.');
    }
}

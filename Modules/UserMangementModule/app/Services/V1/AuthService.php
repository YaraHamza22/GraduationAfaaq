<?php

namespace Modules\UserMangementModule\Services\V1;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Modules\UserMangementModule\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Modules\UserMangementModule\DTOs\StudentDTO;
use Modules\UserMangementModule\Enums\UserRole;

class AuthService
{
    public function register(array $data): array
    {
        $studentDTO = StudentDTO::fromArray($data);

        return DB::transaction(function () use ($data, $studentDTO) {
            $userData = $studentDTO->userData();
            $studentData = $studentDTO->studentData();
            $userData['password'] = Hash::make($data['password']);

            $user = User::create($userData);
            $user->studentProfile()->create($studentData);
            $user->assignRole(UserRole::STUDENT->value);

            $token = JWTAuth::fromUser($user);

            $user->load([
                'roles.permissions',
                'studentProfile',
            ]);

            return [
                'status' => 'success',
                'user' => $user,
                'token' => $token,
                'redirect_to' => '/student/dashboard',
            ];
        });
    }

    public function login(array $credentials): array
    {
        $authCredentials = [
            'email' => $credentials['email'] ?? null,
            'password' => $credentials['password'] ?? null,
        ];

        if (! $token = JWTAuth::attempt($authCredentials)) {
            return [
                'status' => 'error',
                'message' => 'invalid credentials',
                'user' => null,
                'token' => null,
            ];
        }

        $user = auth()->user();
        $user->loadMissing('roles');

        return [
            'status' => 'success',
            'user' => $user,
            'role' => $user->getRoleNames()->first(),
            'token' => $token,
        ];
    }

    public function sendPasswordResetLink(array $payload): array
    {
        $status = Password::broker('users')->sendResetLink([
            'email' => $payload['email'],
        ]);

        return [
            'status' => $status === Password::RESET_LINK_SENT ? 'success' : 'error',
            'broker_status' => $status,
            'message' => __($status),
        ];
    }

    public function resetPassword(array $payload): array
    {
        $status = Password::broker('users')->reset(
            [
                'email' => $payload['email'],
                'token' => $payload['token'],
                'password' => $payload['password'],
                'password_confirmation' => $payload['password_confirmation'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return [
            'status' => $status === Password::PASSWORD_RESET ? 'success' : 'error',
            'broker_status' => $status,
            'message' => __($status),
        ];
    }
}
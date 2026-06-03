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
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\AuditorRoleSeeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\InstructorRoleSeeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\PermissionSeeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\StudentRoleSeeder;
use Modules\UserMangementModule\Database\Seeders\RolesAndPermissions\SuperAdminRoleSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
                'roles:id,name,guard_name',
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


   /* public function login(array $credentials): array
    {

        $email = $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;
        $authCredentials = [
            'email' => $email,
            'password' => $password,
        ];

        if (! $token = JWTAuth::attempt($authCredentials)) {
            $user = is_string($email) ? User::query()->where('email', $email)->first() : null;

            if (! $user || ! is_string($password) || $password === '') {
                return [
                    'status' => 'error',
                    'message' => 'invalid credentials',
                    'user' => null,
                    'token' => null,
                ];
            }

            $storedPassword = (string) ($user->password ?? '');
            $matchesLegacyPlainText = $storedPassword !== '' && hash_equals($storedPassword, $password);

            if (! $matchesLegacyPlainText) {
                return [
                    'status' => 'error',
                    'message' => 'invalid credentials',
                    'user' => null,
                    'token' => null,
                ];
            }

            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            $token = JWTAuth::fromUser($user);
        }

        $user = auth()->user();

        if (! $user && isset($user) && $user instanceof User) {
            auth()->setUser($user);
        }

        $user = $user ?: (is_string($email) ? User::query()->where('email', $email)->first() : null);

        if (! $user) {
            return [
                'status' => 'error',
                'message' => 'invalid credentials',
                'user' => null,
                'token' => null,
            ];
        }

        $user->loadMissing('roles');

       return [
    'status' => 'success',
    'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ],
         'role' => $user->getRoleNames()->first(),
         'token' => $token,
       ];
    }*/
       public function login(array $credentials): array
{
    $email = $credentials['email'] ?? null;
    $password = $credentials['password'] ?? null;

    $authCredentials = [
        'email' => $email,
        'password' => $password,
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

    if (! $user) {
        return [
            'status' => 'error',
            'message' => 'invalid credentials',
            'user' => null,
            'token' => null,
        ];
    }

    $user->loadMissing([
        'roles:id,name,guard_name',
        'studentProfile',
    ]);

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

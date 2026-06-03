<?php

namespace Modules\UserMangementModule\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\UserMangementModule\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use Modules\UserMangementModule\Http\Requests\Api\V1\Auth\LoginRequest;
use Modules\UserMangementModule\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use Modules\UserMangementModule\Http\Requests\Api\V1\Auth\RegisterRequest;
use Modules\UserMangementModule\Services\V1\AuthService;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected $authService;
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request)
    {
        $data = $this->authService->register($request->validated());
        if ($data['status'] === 'error') {
            return self::error($data['message'] ?? 'registration failed', 422, $data);
        }

        return self::success($data, 'registered successfully', 201);
    }


    public function login(LoginRequest $request)
    {
        $data = $this->authService->login($request->validated());
        if ($data['status'] === 'error') {
            return self::error($data['message'] ?? 'invalid credentials', 401, $data);
        }

        return self::success($data, 'successfully logged in');
    }

    public function logout()
    {

        auth()->guard('api')->logout();
        return self::success(null, 'Successfully logged out');
    }

    public function profile()
    {
        return self::success(auth()->user());
    }

    public function refresh()
    {
        $token = JWTAuth::getToken();
        if (! $token) {
            return self::error('Token not provided', 401);
        }

        $token = JWTAuth::refresh($token);
        $data = [
            'status' => 'success',
            'user'   => auth()->user(),
            'token'  => $token,
        ];

        return self::success($data);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $result = $this->authService->sendPasswordResetLink($request->validated());

        if ($result['status'] === 'error') {
            return self::error($result['message'], 422, $result);
        }

        return self::success($result, $result['message']);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $result = $this->authService->resetPassword($request->validated());

        if ($result['status'] === 'error') {
            return self::error($result['message'], 422, $result);
        }

        return self::success($result, $result['message']);
    }
}
<?php

use Illuminate\Support\Facades\Route;
use Modules\UserMangementModule\Http\Controllers\UserMangementModuleController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('usermangementmodules', UserMangementModuleController::class)->names('usermanagementmodule');
});

Route::get('/reset-password/{token}', function (string $token) {
    return response()->json([
        'token' => $token,
        'email' => request('email'),
        'message' => 'Use this token and email in the API reset-password endpoint.',
    ]);
})->name('password.reset');

<?php

use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'message' => 'Health check passed.',
    ]);
});

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'message' => 'Afaaq API is running.',
    ]);
});

<?php

use App\Http\Controllers\Api\SocialAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/auth/google', [SocialAuthController::class, 'google']);
Route::post('/auth/apple', [SocialAuthController::class, 'apple']);

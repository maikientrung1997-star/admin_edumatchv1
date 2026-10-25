<?php

use App\Http\Controllers\Api\AdministrativeAreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

// Keep both /api/auth/* and legacy /api/* clients on the same implementation.
$authRoutes = function () {
    Route::get('/google/challenge', [AuthController::class, 'googleChallenge'])->middleware('throttle:google-challenge');
    Route::post('/google', [AuthController::class, 'google'])->middleware('throttle:google-auth');
    Route::post('/register/send-otp', [AuthController::class, 'sendRegistrationOtp'])->middleware('throttle:registration-otp-send');
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:password-reset-send');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:password-reset-verify');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/change-password', [PasswordController::class, 'change'])->middleware(EnsureActiveAccount::class);
    });
};

Route::prefix('auth')->group($authRoutes);
Route::group([], $authRoutes);

Route::get('/administrative-areas', [AdministrativeAreaController::class, 'index']);
Route::controller(\App\Http\Controllers\Api\MasterDataController::class)->group(function () {
    Route::get('/subjects', 'subjects');
    Route::get('/education-levels', 'levels');
    Route::get('/banners', 'banners');
    Route::get('/administrative-areas/provinces', 'provinces');
    Route::get('/administrative-areas/districts/{province}', 'districts');
    Route::get('/administrative-areas/wards/{parent}', 'wards');
    Route::get('/provider-verifications', 'verificationStatuses');
    Route::get('/providers/{user}', 'provider');
});
Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
    Route::get('/verifications', [\App\Http\Controllers\Api\VerificationController::class, 'index']);
    Route::post('/verifications', [\App\Http\Controllers\Api\VerificationController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/verifications/documents/{document}', [\App\Http\Controllers\Api\VerificationController::class, 'document']);
    Route::get('/notifications', [\App\Http\Controllers\Api\AccountNotificationController::class, 'index']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\AccountNotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\AccountNotificationController::class, 'read']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/student/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'avatar']);
});

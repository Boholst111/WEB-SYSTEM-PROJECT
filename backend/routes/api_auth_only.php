<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
        'environment' => app()->environment(),
    ]);
});

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\Auth\RegisterController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
    Route::post('/forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset']);
    Route::get('/verify-email/{id}/{hash}', [App\Http\Controllers\Auth\RegisterController::class, 'verifyEmail'])
        ->middleware(['signed'])->name('verification.verify');
    Route::post('/validate-reset-token', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'validateResetToken']);
});

// Protected authentication routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout']);
        Route::post('/logout-all', [App\Http\Controllers\Auth\LoginController::class, 'logoutAll']);
        Route::post('/refresh', [App\Http\Controllers\Auth\LoginController::class, 'refresh']);
        Route::get('/me', [App\Http\Controllers\Auth\LoginController::class, 'me']);
        Route::post('/change-password', [App\Http\Controllers\Auth\ResetPasswordController::class, 'changePassword']);
        Route::post('/resend-verification', [App\Http\Controllers\Auth\RegisterController::class, 'resendVerification']);
    });
});
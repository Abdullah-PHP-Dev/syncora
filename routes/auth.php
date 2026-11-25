<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    // Six-digit code screen (App\Services\Auth\EmailVerification).
    Route::get('verify-email', [EmailVerificationController::class, 'show'])
        ->name('verification.notice');

    Route::post('verify-email/code', [EmailVerificationController::class, 'verifyCode'])
        ->middleware('throttle:10,1')
        ->name('verification.code');

    Route::post('verify-email/change-email', [EmailVerificationController::class, 'changeEmail'])
        ->middleware('throttle:5,10')
        ->name('verification.change-email');

    // Links sent before the code + magic-link email (signed, signed-in only).

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

// The email's "Verify My Email" link: works signed in or not, on any device. GET only shows a
// confirmation page (mail scanners open links); the POST verifies.
Route::get('email/verify/link/{token}', [EmailVerificationController::class, 'showLink'])
    ->middleware('throttle:30,1')->where('token', '[A-Za-z0-9]{1,128}')
    ->name('verification.link');
Route::post('email/verify/link/{token}', [EmailVerificationController::class, 'confirmLink'])
    ->middleware('throttle:10,1')->where('token', '[A-Za-z0-9]{1,128}')
    ->name('verification.link.confirm');

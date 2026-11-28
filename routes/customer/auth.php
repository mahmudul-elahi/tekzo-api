<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Customer\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Customer\Auth\LoginController;
use App\Http\Controllers\Api\V1\Customer\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Customer\Auth\SocialLoginController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1/auth', 'as' => 'auth.'], function (Router $router) {

    $router->post('login', [LoginController::class, 'login'])->name('login')->middleware('throttle:auth');
    $router->post('social/{provider}', [SocialLoginController::class, 'login'])->name('social-login')->middleware('throttle:auth');
    $router->post('register', [RegisterController::class, 'register'])->name('register')->middleware('throttle:auth');
    $router->post('forgot-password', [ForgotPasswordController::class, 'forgot'])->name('forgot-password')->middleware('throttle:otp-send');
    $router->post('verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->name('forgot.verify-otp')->middleware('throttle:otp-verify');
    $router->post('confirm-password', [ForgotPasswordController::class, 'confirmPassword'])->name('forgot.confirm-password')->middleware('throttle:auth');
});

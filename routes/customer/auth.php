<?php

use App\Http\Controllers\Customer\Auth\ForgotPasswordController;
use App\Http\Controllers\Customer\Auth\LoginController;
use App\Http\Controllers\Customer\Auth\RegisterController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'v1/auth', 'as' => 'auth.'], function (Router $router) {

    $router->post('login', [LoginController::class, 'login'])->name('login');
    $router->post('register', [RegisterController::class, 'register'])->name('register');
    $router->post('forgot-password', [ForgotPasswordController::class, 'forgot'])->name('forgot-password');
    $router->post('verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->name('forgot.verify-otp');
    $router->post('confirm-password', [ForgotPasswordController::class, 'confirmPassword'])->name('forgot.confirm-password');
});

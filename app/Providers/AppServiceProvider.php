<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('otp-send', function (Request $request) {
            $target = (string) ($request->input('email') ?? $request->input('phone_number') ?? $request->input('phone') ?? '');

            return [
                Limit::perMinutes(10, 3)->by($request->ip()),
                Limit::perMinutes(10, 3)->by($target),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $target = (string) ($request->input('email') ?? $request->input('phone_number') ?? $request->input('phone') ?? '');

            return Limit::perMinutes(10, 5)->by($request->ip().'|'.$target);
        });

        RateLimiter::for('public-write', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('invoice-tracking', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}

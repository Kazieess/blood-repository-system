<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('staff-login', fn (Request $request): array => [
            Limit::perMinute(5)->by(hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip())),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('staff-api', fn (Request $request) => Limit::perMinute(120)->by((string) ($request->user()?->id ?? $request->ip())));
    }
}

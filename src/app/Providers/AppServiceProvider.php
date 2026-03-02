<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $loginRateLimitResponse = function (Request $request)
        {
            if ($request->expectsJson()) {
                return response()->json(
                    [
                        'message' => 'You have reached the maximum number of login attempts.'
                    ],
                    429
                );
            }

            return back()
                ->withErrors(['email' => 'You have reached the maximum number of login attempts.'])
                ->withInput($request->except('password'));
        };

        RateLimiter::for(
            'login',
            function (Request $request) use ($loginRateLimitResponse)
            {
                return [
                    Limit::perMinute(5)->by($request->input('email'))->response($loginRateLimitResponse),
                    Limit::perMinute(50)->by($request->ip())->response($loginRateLimitResponse)
                ];
            }
        );

        RateLimiter::for(
            'password-reset-request',
            function (Request $request)
            {
                return [
                    Limit::perHour(3)->by($request->input('email')),
                    Limit::perHour(6)->by($request->ip())
                ];
            }
        );

        RateLimiter::for(
            'password-reset',
            function (Request $request)
            {
                return [
                    Limit::perHour(3)->by($request->input('email')),
                    Limit::perHour(6)->by($request->ip())
                ];
            }
        );

        Password::defaults(function ()
        {
            if ($this->app->isLocal()) {
                return Password::min(8);
            }

            return Password::min(8)
                ->uncompromised()
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();
        });
    }
}

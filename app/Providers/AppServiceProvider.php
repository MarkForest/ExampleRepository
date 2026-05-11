<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Repositories\AccountRepository;
use App\Repositories\PaymentRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Tests\Mocks\AccountRepositoryMock;
use Tests\Mocks\PaymentRepositoryMock;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('testing')) {
            $this->app->bind(PaymentRepositoryInterface::class, PaymentRepositoryMock::class);
            $this->app->bind(AccountRepositoryInterface::class, AccountRepositoryMock::class);
        } else {
            $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
            $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-without-cache', function (Request $request): Limit {
            return $request->user()
                ? Limit::perMinute(120)->by((string) $request->user()->id)
                : Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('api-with-cache', function (Request $request): Limit {
            return $request->user()
                ? Limit::perMinute(120)->by((string) $request->user()->id)
                : Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('reports', function (Request $request): Limit {
            return Limit::perMinute(3)->by((string) ($request->user()?->id ??
                $request->ip()));
        });

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) $request->ip());
        });
    }
}

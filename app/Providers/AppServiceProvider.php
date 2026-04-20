<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Repositories\AccountRepository;
use App\Repositories\PaymentRepository;
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
    public function boot(): void {}
}

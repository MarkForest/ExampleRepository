<?php

namespace App\Providers;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Events\PaymentCompleted;
use App\Listeners\LogPaymentToAudit;
use App\Listeners\SendDelayedPaymentNotification;
use App\Listeners\SendPaymentConfirmationNotification;
use App\Models\Payment;
use App\Repositories\AccountRepository;
use App\Repositories\PaymentRepository;
use Illuminate\Support\ServiceProvider;
use Tests\Mocks\PaymentRepositoryMock;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
//        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);

//        $this->app->bind(PaymentRepositoryInterface::class, function () {
//            if (env('APP_ENV') === 'production' || env('APP_ENV') === 'local') {
//                return new PaymentRepository();
//            } else {
//                return new PaymentRepositoryMock();
//            }
//        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}

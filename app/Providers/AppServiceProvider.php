<?php

namespace App\Providers;

use App\Events\PaymentCompleted;
use App\Listeners\LogPaymentToAudit;
use App\Listeners\SendDelayedPaymentNotification;
use App\Listeners\SendPaymentConfirmationNotification;
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
//        Event::listen(PaymentCompletedEvent::class, [
//            SendPaymentConfirmationNotificationListener::class,
//            LogPaymentToAuditListener::class,
//            SendDelayedPaymentNotificationListener::class,
//        ]);
//
//        Event::listen(PaymentCompletedEvent2::class, [
//            SendPaymentConfirmationNotificationListener::class,
//            LogPaymentToAuditListener::class,
//            SendDelayedPaymentNotificationListener::class,
//        ]);
//
//        Event::listen(PaymentCompletedEvent3::class, [
//            SendPaymentConfirmationNotificationListener::class,
//            LogPaymentToAuditListener::class,
//            SendDelayedPaymentNotificationListener::class,
//        ]);
//        Event::listen(queueable(function (PaymentCompletedEvent $event) {
//            // ...
//        }));
    }
}

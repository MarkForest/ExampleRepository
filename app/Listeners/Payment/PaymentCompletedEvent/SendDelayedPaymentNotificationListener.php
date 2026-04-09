<?php

namespace App\Listeners\Payment\PaymentCompletedEvent;

use App\Events\Payment\PaymentCompletedEvent;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;

class SendDelayedPaymentNotificationListener
{
    /**
     * Handle the event.
     */
    public function handle(PaymentCompletedEvent $event): void
    {
        /** @var User|null $user */
        $user = User::query()->find($event->userId);
        if ($user === null) {
            return;
        }

        $notification = (new PaymentReceivedNotification(
            paymentId: $event->paymentId,
            amount: $event->amount,
            currency: $event->currency
        ))->delay(now()->addMinutes(5));

        $user->notify($notification);
    }
}

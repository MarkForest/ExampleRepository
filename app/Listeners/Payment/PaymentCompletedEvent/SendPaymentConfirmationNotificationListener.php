<?php

namespace App\Listeners\Payment\PaymentCompletedEvent;

use App\Events\Payment\PaymentCompletedEvent;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationNotificationListener
{
    /**
     * Handle the event.
     */
    public function handle(PaymentCompletedEvent $event): void
    {
        $user = User::query()->find($event->userId);
        if ($user === null) {
            Log::warning('User not found for payment notification', [
                'user_id' => $event->userId,
            ]);

            return;
        }

        $user->notify(new PaymentReceivedNotification(
            $event->paymentId,
            $event->amount,
            $event->currency,
        ));
    }
}

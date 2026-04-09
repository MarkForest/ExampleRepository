<?php

namespace App\Listeners\Payment\PaymentCompletedEvent;

use App\Events\Payment\PaymentCompletedEvent;
use App\Models\AuditLog;

class LogPaymentToAuditListener
{
    /**
     * Handle the event.
     */
    public function handle(PaymentCompletedEvent $event): void
    {
        AuditLog::query()->create([
            'payment_id' => $event->paymentId,
            'event_type' => 'payment_completed',
            'payload' => [
                'user_id' => $event->userId,
                'amount' => $event->amount,
                'currency' => $event->currency,
            ],
        ]);
    }
}

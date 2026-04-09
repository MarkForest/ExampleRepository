<?php

namespace App\Events\Payment;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly int $paymentId,
        public readonly string $userId,
        public readonly float $amount,
        public readonly string $currency,
    ) {}
}

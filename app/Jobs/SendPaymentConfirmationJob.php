<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly int $paymentId) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentMailService $paymentMailService): void
    {
        $payment = Payment::query()->find($this->paymentId);
        if ($payment === null) {
            Log::warning('Payment not found for SendPaymentConfirmationJob', [
                'payment_id' => $this->paymentId,
            ]);

            return;
        }

        $paymentMailService->sendPaymentConfirmation($payment);
    }
}

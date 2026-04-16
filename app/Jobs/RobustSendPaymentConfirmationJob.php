<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RobustSendPaymentConfirmationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60, 120, 300]; // у секундах
    public int $timeout = 25;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly int $paymentId) {}

    /**
     * Execute the job.
     * @throws Throwable
     */
    public function handle(PaymentMailService $paymentMailService): void
    {
        /** @var Payment $payment */
        $payment = Payment::query()->find($this->paymentId);
        if ($payment === null) {
            Log::warning('Payment not found for RobustSendPaymentConfirmationJob', [
                'payment_id' => $this->paymentId,
            ]);

            return;
        }

        if ($payment->email_sent_at !== null) {
            Log::info('Skip sending email: already sent', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        try {
            $paymentMailService->sendPaymentConfirmation($payment);
        } catch (Throwable $exception) {
            Log::error('Failed to send payment confirmation email', [
                'payment_id' => $this->paymentId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}

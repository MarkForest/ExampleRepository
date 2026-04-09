<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class LogPaymentToAuditJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 12, 14, 15, 16]; // у секундах
    public int $timeout = 25;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly int $paymentId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payment = Payment::query()->find($this->paymentId);
        if ($payment === null) {
            Log::warning('Payment not found for RobustSendPaymentConfirmationJob', [
                'payment_id' => $this->paymentId,
            ]);

            return;
        }

        AuditLog::query()->create([
            'payment_id' => $payment->id,
            'event_type' => 'payment_completed',
            'payload' => [
                'user_id' => $payment->user_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ],
        ]);
    }
}

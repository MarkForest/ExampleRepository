<?php

namespace App\Services;

use App\Events\Payment\PaymentCompletedEvent;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    /**
     * @param array $data
     * @return Payment|null
     */
    public function processPayment(array $data): ?Payment
    {
        $payment = DB::transaction(function () use ($data, &$payment) {
            return Payment::query()->create([
                'user_id' => $data['user_id'],
                'amount' => $data['amount'],
                'status' => $data['status'],
                'currency' => $data['currency'],
            ]);
        });

        if (null !== $payment) {
            event(new PaymentCompletedEvent(
                $payment->id,
                $payment->user_id,
                $payment->amount,
                $payment->currency,
            ));
        }

        return $payment;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\GoodPattern;

use App\Models\Account;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PaymentCommissionService
{
    public function createPaymentWithCommission(array $payload): Payment
    {
        /** @var Account|null $account */
        $account = Account::query()->find($payload['account_id'] ?? null);
        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }

        $paymentAmount = (float) ($payload['amount'] ?? 0.0);
        if ($account->balance < $paymentAmount) {
            throw new RuntimeException('Insufficient funds.');
        }

        $commissionRate = $paymentAmount > 1000.0 ? 0.01 : 0.0;
        $commission = $paymentAmount * $commissionRate;

        /** @var Payment $payment */
        $payment = DB::transaction(static function () use (
            $paymentAmount,
            $commission,
            $payload,
            $account
        ): Payment {
            $payment = Payment::query()->create([
                'currency'   =>  $payload['currency'] ?? 'USD',
                'description' => $payload['description'] ?? '',
                'amount'     =>  $paymentAmount,
                'account_id' =>  $account->id,
                'commission' => $commission,
                'status' => 'completed',
            ]);

            $account->balance -= $paymentAmount + $commission;
            $account->save();

            return $payment;
        });
        return $payment;
    }
}

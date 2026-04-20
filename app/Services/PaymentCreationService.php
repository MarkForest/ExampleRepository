<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PaymentCreationService
{
    public function createPaymentWithCommission(
        int $accountId,
        float $amount,
        string $currency
    ): Payment {
        return DB::transaction(static function () use (
            $accountId,
            $amount,
            $currency
        ): Payment {

            /** @var User $user */
            $user = User::query()->first();
            if ($user === null) {
                $user = User::factory()->create();
            }

            $payment = Payment::query()->create([
                'user_id' => $user->id,
                'account_id' => $accountId,
                'amount' => $amount,
                'currency' => $currency,
                'commission' => $amount >= 1000.0 ? round($amount * 0.01, 2) : 0.0,
                'status' => 'created',
            ]);
            return $payment;
        });
    }
}

<?php

namespace App\Http\Controllers\AntiPattern;

use App\Events\Payment\PaymentCompletedEvent;
use App\Models\Account;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        // Валідація HTTP-рівня впереміш з логікою
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        // Перевірка балансу прямо в контролері
        /** @var Account|null $account */
        $account = Account::query()->find($validated['account_id']);
        if ($account === null) {
            return response()->json([
                'message' => 'Account not found',
            ], 404);
        }

        // Розрахунок комісії тут же
        $commissionRate = $validated['amount'] > 1000 ? 0.01 : 0.0;
        $commission = $validated['amount'] * $commissionRate;

        // Транзакція БД + створення платежу + оновлення балансу в контролері
        /** @var Payment $payment */
        $payment = DB::transaction(static function () use (
            $validated,
            $account, $commission
        ): Payment {
            $payment = Payment::query()->create([
                'account_id' => $account->id,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'description' => $validated['description'] ?? '',
                'commission' => $commission,
                'status' => 'completed',
            ]);
            $account->balance -= ($validated['amount'] + $commission);
            $account->save();
            return $payment;
        });

        // Подія напряму з контролера
        event(new PaymentCompletedEvent(
            $payment->id,
            $payment->account_id,
            (string)$payment->amount,
            $payment->currency
        ));

        return response()->json([
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'commission' => $payment->commission,
            'status' => $payment->status,
        ]);
    }
}

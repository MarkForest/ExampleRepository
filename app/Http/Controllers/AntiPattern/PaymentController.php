<?php

declare(strict_types=1);

namespace App\Http\Controllers\AntiPattern;

use App\Models\Account;
use Illuminate\Http\Request;

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




    }
}

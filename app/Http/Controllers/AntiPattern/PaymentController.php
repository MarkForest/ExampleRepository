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

        return response()->json([
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'commission' => $payment->commission,
            'status' => $payment->status,
        ]);
    }
}

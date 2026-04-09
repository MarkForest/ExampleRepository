<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Mail;

class PaymentMailService
{
    public function sendPaymentConfirmation(Payment $payment): void
    {
        Mail::raw(
            "Ваш платіж №$payment->id на суму $payment->amount успішно проведений.",
            function ($message) use ($payment) {
                $message->to($payment->user()->first()->email)->subject('Payment Confirmation');
            }
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PaymentMailService
{
    public function sendPaymentConfirmation(Payment $payment): void
    {
        Mail::raw(
            "Ваш платіж №$payment->id на суму $payment->amount успішно проведений.",
            function ($message) use ($payment): void {
                /** @var User $user */
                $user = $payment->user()->first();
                $message->to($user->email)->subject('Payment Confirmation');
            }
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Data\Api\V1\CreatePaymentData;
use App\DTO\Api\V1\CreatePaymentDTO;
use App\Events\Payment\PaymentCompletedEvent;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class PaymentService
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository
    ) {}

    /**
     * @param CreatePaymentData $data
     * @return Payment|null
     */
    public function processPayment(CreatePaymentData $data): ?Payment
    {

        $moneyObject = $data->getMoneyObject();
        $commission = $moneyObject->isGreaterThan('1000') ? 0.01 : 0.0;
        $data->setMoneyObject($moneyObject->withCommission($commission));

        /** @var Payment $payment */
        $payment = DB::transaction(function () use (
            $data,
            $commission
        ): Payment {
            return $this->paymentRepository->create([
                'user_id' => $data->getUserId(),
                'amount' => $data->getAmount(),
                'currency' => $data->getCurrency(),
                'description' => $data->getDescription(),
                'commission' => $commission,
                'status' => 'completed',
            ]);
        });

        event(new PaymentCompletedEvent(
            (int) $payment->id,
            (string) $payment->account_id,
            (float) $payment->amount,
            (string) $payment->currency
        ));

        return $payment;
    }

    public function deletePayment(Payment $payment)
    {
        $payment->delete();
    }
}

<?php

namespace App\Services;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\DTO\Payment\CreatePaymentDTO;
use App\Events\Payment\PaymentCompletedEvent;
use App\Models\Account;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly AccountRepositoryInterface $accountRepository,
    ) {}

    /**
     * @param CreatePaymentDTO $createPaymentDTO
     * @return Payment|null
     */
    public function processPayment(CreatePaymentDTO $createPaymentDTO): ?Payment
    {
        /** @var Account|null $account */
        $account = Account::query()->find($createPaymentDTO->getAccountId());
        if ($account === null) {
            return null;
        }

        // Розрахунок комісії тут же
        $commissionRate = $createPaymentDTO->getAmount() > 1000 ? 0.01 : 0.0;
        $commission = $createPaymentDTO->getAmount() * $commissionRate;

        // Транзакція БД + створення платежу + оновлення балансу в контролері
        /** @var Payment $payment */
        $payment = DB::transaction(static function () use (
            $createPaymentDTO,
            $account, $commission
        ): Payment {
            $payment = $this->paymentRepository->create($createPaymentDTO->toArray());

            $amount = $createPaymentDTO->getAmount() + $commission;
            $this->accountRepository->decrementBalance($account->id, (string) $amount);

            return $payment;
        });

        // Подія напряму з контролера
        event(new PaymentCompletedEvent(
            $payment->id,
            $payment->account_id,
            (string)$payment->amount,
            $payment->currency
        ));

        return $payment;
    }
}

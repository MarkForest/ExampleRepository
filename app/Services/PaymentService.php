<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\DTO\Payment\CreatePaymentDTO;
use App\Events\Payment\PaymentCompletedEvent;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class PaymentService
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private AccountRepositoryInterface $accountRepository,
    ) {}

    /**
     * @param CreatePaymentDTO $createPaymentDTO
     * @return Payment|null
     */
    public function processPayment(CreatePaymentDTO $createPaymentDTO): ?Payment
    {
        $account = $this->accountRepository->findById($createPaymentDTO->getAccountId());
        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }
        if ($account->balance < (float) $createPaymentDTO->getAmount()) {
            throw new RuntimeException('Insufficient funds.');
        }

        $moneyObject = $createPaymentDTO->getMoneyObject();
        $commission = $moneyObject->isGreaterThan('1000') ? 0.01 : 0.0;
        $createPaymentDTO->setMoneyObject($moneyObject->withCommission($commission));

        /** @var Payment $payment */
        $payment = DB::transaction(function () use (
            $createPaymentDTO,
            $account,
            $commission
        ): Payment {
            $payment = $this->paymentRepository->create([
                'account_id' => $account->id,
                'amount' => $createPaymentDTO->getAmount(),
                'currency' => $createPaymentDTO->getCurrency(),
                'description' => $createPaymentDTO->getDescription(),
                'commission' => $commission,
                'status' => 'completed',
            ]);

            $this->accountRepository->decrementBalance(
                $account->id,
                (string) ($createPaymentDTO->getAmount() + $commission)
            );

            return $payment;
        });

        event(new PaymentCompletedEvent(
            $payment->id,
            $payment->account_id,
            (string) $payment->amount,
            $payment->currency
        ));

        return $payment;
    }

    /**
     * @param CreatePaymentDTO $createPaymentDTO
     * @return Payment|null
     */
    public function processPayment2(CreatePaymentDTO $createPaymentDTO): ?Payment
    {
        $account = $this->accountRepository->findById($createPaymentDTO->getAccountId());
        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }
        if ($account->balance < (float) $createPaymentDTO->getAmount()) {
            throw new RuntimeException('Insufficient funds.');
        }

        $moneyObject = $createPaymentDTO->getMoneyObject();
        $commission = $moneyObject->isGreaterThan('1000') ? 0.01 : 0.0;
        $createPaymentDTO->setMoneyObject($moneyObject->withCommission($commission));

        /** @var Payment $payment */
        $payment = DB::transaction(function () use (
            $createPaymentDTO,
            $account,
            $commission
        ): Payment {
            $payment = $this->paymentRepository->create([
                'account_id' => $account->id,
                'amount' => $createPaymentDTO->getAmount(),
                'currency' => $createPaymentDTO->getCurrency(),
                'description' => $createPaymentDTO->getDescription(),
                'commission' => $commission,
                'status' => 'completed',
            ]);

            $this->accountRepository->decrementBalance(
                $account->id,
                (string) ($createPaymentDTO->getAmount() + $commission)
            );

            return $payment;
        });

        event(new PaymentCompletedEvent(
            $payment->id,
            $payment->account_id,
            (string) $payment->amount,
            $payment->currency
        ));

        return $payment;
    }

    /**
     * @param CreatePaymentDTO $createPaymentDTO
     * @return Payment|null
     */
    public function processPayment3(CreatePaymentDTO $createPaymentDTO): ?Payment
    {
        $account = $this->accountRepository->findById($createPaymentDTO->getAccountId());
        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }
        if ($account->balance < (float) $createPaymentDTO->getAmount()) {
            throw new RuntimeException('Insufficient funds.');
        }

        $moneyObject = $createPaymentDTO->getMoneyObject();
        $commission = $moneyObject->isGreaterThan('1000') ? 0.01 : 0.0;
        $createPaymentDTO->setMoneyObject($moneyObject->withCommission($commission));

        /** @var Payment $payment */
        $payment = DB::transaction(function () use (
            $createPaymentDTO,
            $account,
            $commission
        ): Payment {
            $payment = $this->paymentRepository->create([
                'account_id' => $account->id,
                'amount' => $createPaymentDTO->getAmount(),
                'currency' => $createPaymentDTO->getCurrency(),
                'description' => $createPaymentDTO->getDescription(),
                'commission' => $commission,
                'status' => 'completed',
            ]);

            $this->accountRepository->decrementBalance(
                $account->id,
                (string) ($createPaymentDTO->getAmount() + $commission)
            );

            return $payment;
        });

        event(new PaymentCompletedEvent(
            $payment->id,
            $payment->account_id,
            (string) $payment->amount,
            $payment->currency
        ));

        return $payment;
    }
}

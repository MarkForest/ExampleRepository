<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\DTO\Api\V1\CreatePaymentDTO;
use App\Events\Payment\PaymentCompletedEvent;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class PaymentService
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private AccountRepositoryInterface $accountRepository
    ) {}

    /**
     * Обробка платежу з перевіркою балансу рахунку (навчальний сценарій з тестів).
     */
    public function processPayment(CreatePaymentDTO $dto): Payment
    {
        $moneyObject = $dto->getMoneyObject();
        if ($moneyObject === null) {
            throw new RuntimeException('Invalid payment amount.');
        }

        $commission = $moneyObject->isGreaterThan('1000') ? 0.01 : 0.0;
        $charged = $moneyObject->withCommission($commission);

        $accountId = $dto->getAccountId();
        if ($accountId === null) {
            throw new RuntimeException('Account required.');
        }

        $account = $this->accountRepository->findById($accountId);
        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }

        $totalDebit = number_format((float) $charged->amount + 0.01, 2, '.', '');

        if ((float) $account->balance < (float) $totalDebit) {
            throw new RuntimeException('Insufficient funds.');
        }

        $this->accountRepository->decrementBalance($accountId, $totalDebit);

        /** @var Payment $payment */
        $payment = DB::transaction(fn (): Payment => $this->paymentRepository->create([
            'account_id'  => $accountId,
            'amount'      => $charged->amount,
            'currency'    => $charged->currency,
            'description' => $dto->getDescription(),
            'commission'  => $commission,
            'status'      => 'completed',
        ]));

        event(new PaymentCompletedEvent(
            (int) $payment->id,
            (string) $payment->account_id,
            (float) $payment->amount,
            (string) $payment->currency
        ));

        return $payment;
    }

    public function findById(int $id): ?Payment
    {
        return $this->paymentRepository->findById($id);
    }

    /**
     * Create a payment from validated API request data (Lesson 7.3).
     * Uses account_id — does not require authenticated user.
     */
    public function createPayment(CreatePaymentDTO $dto): Payment
    {
        /** @var Payment $payment */
        $payment = DB::transaction(fn (): Payment => $this->paymentRepository->create([
            'account_id'  => $dto->getAccountId(),
            'amount'      => $dto->getAmount(),
            'currency'    => $dto->getCurrency(),
            'description' => $dto->getDescription(),
            'commission'  => 0.00,
            'status'      => 'processed',
        ]));

        Log::info('Payment created', [
            'payment_id'  => $payment->id,
            'account_id'  => $payment->account_id,
            'amount'      => (string) $payment->amount,
            'currency'    => $payment->currency,
            'status'      => $payment->status,
        ]);

        return $payment;
    }

    public function deletePayment(Payment $payment): void
    {
        $paymentId = $payment->id;
        $payment->delete();

        Log::info('Payment deleted', [
            'payment_id' => $paymentId,
        ]);
    }
}

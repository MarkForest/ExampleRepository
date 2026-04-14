<?php

namespace Tests\Mocks;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;

class PaymentRepositoryMock implements PaymentRepositoryInterface
{
    /**
     * @var array<Payment>
     */
    private array $payments = [];

    public function create(array $data): Payment
    {
        $payment = new Payment($data);
        $payment->id = count($this->payments) + 1;
        $this->payments[$payment->id] = $payment;

        return $payment;
    }

    public function findById(int $id): ?Payment
    {
        return $this->payments[$id] ?? null;
    }
}

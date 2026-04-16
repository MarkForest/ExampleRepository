<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        /** @var Payment $payment */
        $payment = Payment::query()->create($data);
        return $payment;
    }

    public function findById(int $id): ?Payment
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()->find($id);
        return $payment;
    }
}

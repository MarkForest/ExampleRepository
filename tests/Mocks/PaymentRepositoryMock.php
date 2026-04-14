<?php

namespace Tests\Mocks;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;

class PaymentRepositoryMock implements PaymentRepositoryInterface
{

    public function create(array $data)
    {
        new Payment();
        return true;
    }

    public function findById(int $id)
    {
        return (object)['id' => $id, 'balance' => 0];
    }
}

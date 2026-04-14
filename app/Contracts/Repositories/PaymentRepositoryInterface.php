<?php

namespace App\Contracts\Repositories;

use App\Repositories\PaymentRepository;
use Illuminate\Container\Attributes\Bind;
use Tests\Mocks\PaymentRepositoryMock;

#[Bind(PaymentRepository::class)]
#[Bind(PaymentRepositoryMock::class, environments: ['testing'])]
interface PaymentRepositoryInterface
{
    public function create(array $data);
    public function findById(int $id);
}

<?php

namespace App\Contracts\Repositories;

use App\Repositories\PaymentRepository;
use Illuminate\Container\Attributes\Bind;
use Tests\Mocks\PaymentRepositoryMock;

interface PaymentRepositoryInterface
{
    public function create(array $data);
    public function findById(int $id);
}

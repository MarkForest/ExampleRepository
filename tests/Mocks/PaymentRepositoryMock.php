<?php

namespace Tests\Mocks;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;

class PaymentRepositoryMock implements PaymentRepositoryInterface
{
    /** @var list<array<string, mixed>> */
    private static array $createdPayloads = [];

    private static int $nextId = 1;

    public static function reset(): void
    {
        self::$createdPayloads = [];
        self::$nextId = 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function createdPayloads(): array
    {
        return self::$createdPayloads;
    }

    public function create(array $data): Payment
    {
        self::$createdPayloads[] = $data;

        return new Payment(array_merge($data, ['id' => self::$nextId++]));
    }

    public function findById(int $id)
    {
        return null;
    }
}

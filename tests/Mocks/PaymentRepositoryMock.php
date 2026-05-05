<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;

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

    public function findById(int $id): ?Payment
    {
        return null;
    }

    public function paginateByAccountId(int $accountId, int $perPage): LengthAwarePaginator
    {
        return new ConcretePaginator([], 0, $perPage, 1);
    }
}

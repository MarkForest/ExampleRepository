<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use RuntimeException;

class AccountRepositoryMock implements AccountRepositoryInterface
{
    /** @var array<int, Account> */
    private static array $accounts = [];

    /** @var array<int, array{id: int, amount: string}> */
    private static array $decrementCalls = [];

    private static int $nextId = 1;

    public static function reset(): void
    {
        self::$accounts = [];
        self::$decrementCalls = [];
        self::$nextId = 1;
    }

    public static function seedAccount(int $id, float $balance): Account
    {
        $account = new Account();
        $account->id = $id;
        $account->balance = $balance;

        self::$accounts[$id] = $account;

        return $account;
    }

    /**
     * @return array<int, array{id: int, amount: string}>
     */
    public static function decrementCalls(): array
    {
        return self::$decrementCalls;
    }

    /**
     * @param array{balance: string|float} $data
     */
    public function create(array $data): Account
    {
        $id = self::$nextId++;
        $account = new Account(array_merge($data, ['id' => $id]));
        $account->id = $id;
        $account->exists = true;

        self::$accounts[$id] = $account;

        return $account;
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $items = collect(self::$accounts)->sortKeysDesc()->values()->all();

        return new ConcretePaginator(
            array_slice($items, 0, $perPage),
            count($items),
            $perPage,
            1
        );
    }

    public function findById(int $id): ?Account
    {
        return self::$accounts[$id] ?? null;
    }

    public function decrementBalance(int $id, string $amount): void
    {
        $account = $this->findById($id);

        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }

        $account->balance -= (float) $amount;

        self::$decrementCalls[] = [
            'id' => $id,
            'amount' => $amount,
        ];
    }
}

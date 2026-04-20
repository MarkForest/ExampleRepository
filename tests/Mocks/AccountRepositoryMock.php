<?php

namespace Tests\Mocks;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;
use RuntimeException;

class AccountRepositoryMock implements AccountRepositoryInterface
{
    /** @var array<int, Account> */
    private static array $accounts = [];

    /** @var array<int, array{id: int, amount: string}> */
    private static array $decrementCalls = [];

    public static function reset(): void
    {
        self::$accounts = [];
        self::$decrementCalls = [];
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

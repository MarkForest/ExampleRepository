<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountRepository implements AccountRepositoryInterface
{
    /**
     * @param array{balance: string|float} $data
     */
    public function create(array $data): Account
    {
        /** @var Account $account */
        $account = Account::query()->create($data);

        return $account;
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Account> $paginator */
        $paginator = Account::query()->latest()->paginate($perPage);

        return $paginator;
    }

    public function findById(int $id): ?Account
    {
        /** @var Account|null $account */
        $account = Account::query()->find($id);

        return $account;
    }

    public function decrementBalance(int $id, string $amount): void
    {
        /** @var Account|null $account */
        $account = Account::query()->find($id);
        if ($account === null) {
            return;
        }
        $account->balance -= (float) $amount;
        $account->save();
    }
}

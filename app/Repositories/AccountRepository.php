<?php

namespace App\Repositories;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\Models\Account;

class AccountRepository implements AccountRepositoryInterface
{
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

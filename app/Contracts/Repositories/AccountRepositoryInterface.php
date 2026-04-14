<?php

namespace App\Contracts\Repositories;

use App\Models\Account;

interface AccountRepositoryInterface
{
    public function findById(int $id): ?Account;
    public function decrementBalance(int $id, string $amount): void;
}

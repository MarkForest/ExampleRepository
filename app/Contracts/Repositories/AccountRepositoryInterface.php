<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccountRepositoryInterface
{
    /**
     * @param array{balance: string|float} $data
     */
    public function create(array $data): Account;

    public function paginate(int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id): ?Account;

    public function decrementBalance(int $id, string $amount): void;
}

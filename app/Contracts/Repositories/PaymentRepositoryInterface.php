<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;

    public function findById(int $id): ?Payment;

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginateByAccountId(int $accountId, int $perPage): LengthAwarePaginator;
}

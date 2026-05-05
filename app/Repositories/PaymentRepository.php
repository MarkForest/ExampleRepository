<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        /** @var Payment $payment */
        $payment = Payment::query()->create($data);
        return $payment;
    }

    public function findById(int $id): ?Payment
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()->find($id);
        return $payment;
    }

    public function paginateByAccountId(int $accountId, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Payment> $paginator */
        $paginator = Payment::query()
            ->where('account_id', $accountId)
            ->with('account')
            ->latest()
            ->paginate($perPage);

        return $paginator;
    }

    public function getPayments($accountId)
    {
        // latest == orderBy('created_at', 'desc')
        return Payment::query()->where('account_id', $accountId)->latest()->get();
    }
}

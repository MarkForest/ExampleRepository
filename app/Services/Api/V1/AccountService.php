<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Contracts\Repositories\AccountRepositoryInterface;
use App\DTO\Api\V1\CreateAccountDTO;
use App\Models\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

final readonly class AccountService
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository
    ) {}

    public function paginateAccounts(int $perPage = 20): LengthAwarePaginator
    {
        return $this->accountRepository->paginate($perPage);
    }

    public function createAccount(CreateAccountDTO $dto): Account
    {
        /** @var Account $account */
        $account = $this->accountRepository->create([
            'balance' => $dto->getBalance(),
        ]);

        Log::info('Account created', [
            'account_id' => $account->id,
            'balance'    => (string) $account->balance,
        ]);

        return $account;
    }

    public function findById(int $id): ?Account
    {
        return $this->accountRepository->findById($id);
    }

    public function deleteAccount(Account $account): void
    {
        $accountId = $account->id;
        $account->delete();

        Log::info('Account deleted', [
            'account_id' => $accountId,
        ]);
    }
}

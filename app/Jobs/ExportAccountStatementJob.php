<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ExportAccountStatementJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $accountId,
        private readonly string $periodFrom,
        private readonly string $periodTo,
        private readonly string $taskId,
    ) {}

    public function handle(): void
    {
        Log::info('Account statement export started', [
            'task_id'    => $this->taskId,
            'account_id' => $this->accountId,
            'from'       => $this->periodFrom,
            'to'         => $this->periodTo,
        ]);

        /** @var Account|null $account */
        $account = Account::query()->find($this->accountId);

        if ($account === null) {
            Log::warning('Account statement export: account not found', [
                'task_id'    => $this->taskId,
                'account_id' => $this->accountId,
            ]);

            return;
        }

        // Имитация тяжёлой работы (генерация PDF/CSV, выгрузка в storage и т.п.)
        sleep(3);

        Log::info('Account statement export finished', [
            'task_id'    => $this->taskId,
            'account_id' => $account->id,
            'balance'    => $account->balance ?? null,
        ]);
    }
}

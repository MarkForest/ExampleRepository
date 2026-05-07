<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class CurrencyService
{
    private const CACHE_KEY = 'currencies:list';
    private const CACHE_TTL = 3600;

    public function testExample()
    {
//        $accountId = 1;
//        Cache::forget("accounts:{$accountId}:payments:latest:10");
//        Cache::forget("accounts:{$accountId}:payments:summary:current_month");
    }

    public function getAll(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): Collection {
            //$currencyCodes = Currency::query()->orderBy('code')->pluck('code')
//            return collect($currencyCodes);
            return collect(['USD', 'EUR', 'UAH']);
        });
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

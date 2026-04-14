<?php

namespace App\DTO\GoodPattern;

final readonly class CreatePaymentDTO
{
    public function __construct(
        public int    $accountId,
        public string $amount,
        public string $currency,
        public string $description = ''
    ) {}
}

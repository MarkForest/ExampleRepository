<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final class MoneyObject
{
    public function __construct(
        public string $amount,
        public string $currency
    ) {
        if ((float) $this->amount < 0.0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }
        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be 3-letter code.');
        }
    }

    public function isGreaterThan(string $threshold): bool
    {
        return (float) $this->amount > (float) $threshold;
    }
    public function withCommission(float $rate): self
    {
        $commission = (float) $this->amount * $rate;
        $total = (float) $this->amount + $commission;
        return new self(
            amount: (string) $total,
            currency: $this->currency
        );
    }
}

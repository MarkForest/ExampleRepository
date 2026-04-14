<?php

namespace App\DTO\Payment;

use App\DTO\Contracts\BaseDTOInterface;

final class CreatePaymentDTO implements BaseDTOInterface
{
    private ?int $accountId;
    private ?float $amount;
    private ?string $currency;
    private ?string $description;

    /**
     * @param $accountId
     * @param $amount
     * @param $currency
     * @param $description
     */
    public function __construct($accountId, $amount, $currency, $description)
    {
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->description = $description;
    }

    /**
     * @param array $data
     * @return BaseDTOInterface
     */
    public static function fromArray(array $data): BaseDTOInterface
    {
        $accountId = $data['accountId'] ?? $data['account_id'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        $description = $data['description'] ?? null;

        return new self($accountId, $amount, $currency, $description);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'accountId' => $this->accountId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
        ];
    }

    public static function fromJson(string $json): BaseDTOInterface
    {
        return new self(json_decode($json, true));
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    public function setAccountId(?int $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}

<?php

declare(strict_types=1);

namespace App\DTO\Payment;

use App\Contracts\DTO\BaseDTOInterface;
use App\ValueObjects\MoneyObject;

final class CreatePaymentDTO implements BaseDTOInterface
{
    private ?int $accountId;
    public ?MoneyObject $moneyObject;
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
        $this->moneyObject = new MoneyObject($amount, $currency);
        $this->description = $description;
    }

    /**
     * @param array $data
     * @return array
     */
    public static function parseData(array $data): array
    {
        $accountId = $data['accountId'] ?? $data['account_id'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        $description = $data['description'] ?? null;

        return compact('accountId', 'amount', 'currency', 'description');
    }

    /**
     * @param array $data
     * @return BaseDTOInterface
     */
    public static function fromArray(array $data): BaseDTOInterface
    {
        $parsedData = self::parseData($data);

        return new self(
            $parsedData['accountId'],
            $parsedData['amount'],
            $parsedData['currency'],
            $parsedData['description']
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'accountId' => $this->getAccountId(),
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrency(),
            'description' => $this->getDescription(),
        ];
    }

    public static function fromJson(string $json): BaseDTOInterface
    {
        $data = json_decode($json, true);

        $parsedData = self::parseData($data);

        return new self(
            $parsedData['accountId'],
            $parsedData['amount'],
            $parsedData['currency'],
            $parsedData['description']
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    /**
     * @return MoneyObject|null
     */
    public function getMoneyObject(): ?MoneyObject
    {
        return $this->moneyObject;
    }

    /**
     * @param MoneyObject|null $moneyObject
     */
    public function setMoneyObject(?MoneyObject $moneyObject): void
    {
        $this->moneyObject = $moneyObject;
    }

    public function setAccountId(?int $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function getAmount(): ?string
    {
        return $this->moneyObject->amount;
    }

    public function setAmount(?float $amount): void
    {
        $this->moneyObject->amount = (string) $amount;
    }

    public function getCurrency(): ?string
    {
        return $this->moneyObject->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->moneyObject->currency = $currency;
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

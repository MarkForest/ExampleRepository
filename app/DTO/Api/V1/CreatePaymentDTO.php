<?php

declare(strict_types=1);

namespace App\DTO\Api\V1;

use App\Contracts\DTO\BaseDTOInterface;
use App\ValueObjects\MoneyObject;

final class CreatePaymentDTO implements BaseDTOInterface
{
    private ?int $userId;
    private ?int $accountId;
    public ?MoneyObject $moneyObject;
    private ?string $description;

    /**
     * @param $userId
     * @param $accountId
     * @param $amount
     * @param $currency
     * @param $description
     */
    public function __construct($userId, $accountId, $amount, $currency, $description)
    {
        $this->userId = $userId;
        $this->accountId = $accountId !== null ? (int) $accountId : null;
        $this->moneyObject = new MoneyObject((string)$amount, $currency);
        $this->description = $description;
    }

    /**
     * @param array $data
     * @return array
     */
    public static function parseData(array $data): array
    {
        $userId = $data['userId'] ?? $data['user_id'] ?? null;
        $accountId = $data['accountId'] ?? $data['account_id'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        $description = $data['description'] ?? null;

        return compact('userId', 'accountId', 'amount', 'currency', 'description');
    }

    /**
     * @param array $data
     * @return BaseDTOInterface
     */
    public static function fromArray(array $data): BaseDTOInterface
    {
        $parsedData = self::parseData($data);

        return new self(
            $parsedData['userId'],
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
            'userId' => $this->getUserId(),
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
            $parsedData['userId'],
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

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    public function setAccountId(?int $accountId): void
    {
        $this->accountId = $accountId;
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

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
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

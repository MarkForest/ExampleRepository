<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int|null $account_id
 * @property string $amount
 * @property string $status
 * @property string $currency
 * @property string|null $description
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'account_id'  => $this->account_id,
            'amount'      => (string) $this->amount,
            'currency'    => $this->currency,
            'description' => $this->description,
            'status'      => $this->status,
            'created_at'  => Carbon::parse($this->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}

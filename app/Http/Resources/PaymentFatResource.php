<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Payment
 *
 * @property int         $id
 * @property int|null    $account_id
 * @property int|null    $user_id
 * @property string      $amount
 * @property float       $commission
 * @property string      $status
 * @property mixed       $currency
 * @property string|null $description
 * @property mixed       $email_sent_at
 * @property mixed       $created_at
 * @property mixed       $updated_at
 */
final class PaymentFatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $created = $this->created_at !== null ? Carbon::parse($this->created_at) : null;
        $updated = $this->updated_at !== null ? Carbon::parse($this->updated_at) : null;

        return [
            'id'                   => $this->id,
            'account_id'           => $this->account_id,
            'user_id'              => $this->user_id,
            'amount'               => (string) $this->amount,
            'amount_minor_units'   => (int) round(((float) $this->amount) * 100),
            'commission'           => (float) $this->commission,
            'currency'             => $this->currency,
            'description'          => $this->description,
            'description_length'   => $this->description !== null ? mb_strlen($this->description) : 0,
            'status'               => $this->status,
            'status_label'         => ucfirst((string) $this->status),
            'is_processed'         => $this->status === 'processed',
            'email_sent_at'        => $this->email_sent_at,

            'gateway_payment_id'   => 'gw_' . str_pad((string) $this->id, 12, '0', STR_PAD_LEFT),
            'gateway_raw_response' => [
                'request_id'   => 'req_' . md5((string) $this->id),
                'attempts'     => 1,
                'provider'     => 'demo-gateway',
                'trace'        => str_repeat('debug-payload-', 16),
            ],
            'internal_note'        => 'demo-internal: do not show in UI',
            'audit'                => [
                'created_by_system' => 'finance-api',
                'environment'       => app()->environment(),
                'host'              => gethostname() ?: 'unknown',
            ],

            'created_at'           => $created?->format('Y-m-d H:i:s'),
            'created_at_iso'       => $created?->toIso8601String(),
            'created_at_unix'      => $created?->getTimestamp(),
            'updated_at'           => $updated?->format('Y-m-d H:i:s'),
            'updated_at_iso'       => $updated?->toIso8601String(),
        ];
    }
}

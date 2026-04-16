<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'payment_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

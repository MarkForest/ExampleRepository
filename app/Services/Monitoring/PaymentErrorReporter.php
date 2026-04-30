<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Sentry\State\Scope;

final class PaymentErrorReporter
{
    public function reportPaymentFailure(
        int $paymentId,
        int $userId,
        string $gatewayCode,
        string $correlationId
    ): void {
        $message = 'Payment processing failed at gateway level';
        $context = array_merge(Context::all(), [
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'gateway_code' => $gatewayCode,
            'correlation_id' => $correlationId,
        ]);

        Log::error($message, $context);

        \Sentry\withScope(static function (Scope $scope) use ($userId, $gatewayCode, $message, $context): void {
            $scope->setTag('module', 'payments');
            $scope->setTag('action', 'gateway_failure');
            $scope->setTag('gateway_code', $gatewayCode);
            $scope->setUser(['id' => (string) $userId]);

            foreach ($context as $key => $value) {
                $scope->setExtra((string) $key, $value);
            }

            \Sentry\captureException(new RuntimeException($message));
        });
    }
}

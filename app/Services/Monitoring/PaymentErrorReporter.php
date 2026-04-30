<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

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

        Log::error($message, [
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'gateway_code' => $gatewayCode,
            'correlation_id' => $correlationId,
        ]);

        \Sentry\withScope(static function (Scope $scope) use ($paymentId, $userId, $gatewayCode, $correlationId, $message): void {
            $scope->setTag('module', 'payments');
            $scope->setTag('action', 'gateway_failure');
            $scope->setTag('gateway_code', $gatewayCode);
            $scope->setUser(['id' => (string) $userId]);

            $scope->setExtra('payment_id', $paymentId);
            $scope->setExtra('correlation_id', $correlationId);

            \Sentry\captureException(new RuntimeException($message));
        });
    }
}

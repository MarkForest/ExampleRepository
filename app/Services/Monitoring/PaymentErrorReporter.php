<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Sentry\State\Scope;
use function Sentry\captureException;
use function Sentry\withScope;

final class PaymentErrorReporter
{
    public function reportPaymentFailure(
        int $paymentId,
        int $userId,
        string $gatewayCode,
        string $correlationId
    ): void {
        $message = 'Payment processing failed at gateway level';
        $logContext = [
            'payment_id' => $paymentId,
            'gateway_code' => $gatewayCode,
        ];

        $sentryContext = array_merge(Context::all(), $logContext, [
            'correlation_id' => $correlationId,
            'user_id' => $userId,
        ]);

        Log::error($message, $logContext);

        withScope(static function (Scope $scope) use ($userId, $gatewayCode, $message, $sentryContext): void {
            $scope->setTag('module', 'payments');
            $scope->setTag('action', 'gateway_failure');
            $scope->setTag('gateway_code', $gatewayCode);
            $scope->setUser(['id' => (string) $userId]);

            foreach ($sentryContext as $key => $value) {
                $scope->setExtra((string) $key, $value);
            }

            captureException(new RuntimeException($message));
        });
    }
}

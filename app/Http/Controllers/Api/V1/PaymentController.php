<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTO\Api\V1\CreatePaymentDTO;
use App\Http\Controllers\AntiPattern\Controller;
use App\Http\Requests\Api\V1\Payment\PaymentStoreRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Api\V1\PaymentService;
use App\Services\Monitoring\PaymentErrorReporter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Throwable;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentErrorReporter $paymentErrorReporter
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query()->latest()->paginate(20);
        return PaymentResource::collection($payments);
    }

    /**
     * @throws AuthorizationException
     */
    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $this->authorize('view-all-payments');

        $payments = Payment::query()->latest()->paginate(50);
        return PaymentResource::collection($payments);
    }

    /**
     * Store a newly created payment.
     * @throws AuthorizationException
     */
    public function store(PaymentStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        try {
            $dto = CreatePaymentDTO::fromArray($request->validated());
            $payment = $this->paymentService->createPayment($dto);
        } catch (Throwable $exception) {
            $userId = (int) ($request->user()?->getAuthIdentifier() ?? 0);
            $gatewayCode = 'INTERNAL_ERROR';
            $correlationId = (string) (Context::get('correlation_id') ?? $request->header('X-Correlation-ID') ?? 'unknown');

            $this->paymentErrorReporter->reportPaymentFailure(
                paymentId: 0,
                userId: $userId,
                gatewayCode: $gatewayCode,
                correlationId: $correlationId
            );

            throw $exception;
        }

        return response()->json(['data' => new PaymentResource($payment)], 201);
    }

    /**
     * Display the specified payment.
     * @throws AuthorizationException
     */
    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return response()->json(['data' => new PaymentResource($payment)]);
    }

    /**
     * Remove the specified payment.
     * @throws AuthorizationException
     */
    public function destroy(Payment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);

        $this->paymentService->deletePayment($payment);

        return response()->json(null, 204);
    }

    /**
     * Demo endpoint to trigger PaymentErrorReporter for lesson 8.2.
     */
    public function demoFail(Request $request): JsonResponse
    {
        $correlationId = (string) (Context::get('correlation_id') ?? $request->header('X-Correlation-ID') ?? 'unknown');
        $userId = (int) ($request->user()?->getAuthIdentifier() ?? 0);

        $this->paymentErrorReporter->reportPaymentFailure(
            paymentId: 999999,
            userId: $userId,
            gatewayCode: 'DEMO_FAIL',
            correlationId: $correlationId
        );

        return response()->json([
            'message' => 'Demo payment failure has been reported to logs and Sentry.',
            'correlation_id' => $correlationId,
        ], 202);
    }
}

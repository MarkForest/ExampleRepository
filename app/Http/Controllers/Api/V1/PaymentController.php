<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTO\Api\V1\CreatePaymentDTO;
use App\Http\Controllers\AntiPattern\Controller;
use App\Http\Requests\Api\V1\Payment\PaymentStoreRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Api\V1\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Display a listing of payments.
     */
    public function index(): AnonymousResourceCollection
    {
        $payments = Payment::query()->latest()->paginate(20);

        return PaymentResource::collection($payments);
    }

    /**
     * Store a newly created payment.
     */
    public function store(PaymentStoreRequest $request): JsonResponse
    {
        $dto = CreatePaymentDTO::fromArray($request->validated());
        $payment = $this->paymentService->createPayment($dto);

        return response()->json(['data' => new PaymentResource($payment)], 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(int $id): JsonResponse
    {
        $payment = $this->paymentService->findById($id);

        if ($payment === null) {
            abort(404);
        }

        return response()->json(['data' => new PaymentResource($payment)]);
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Payment $payment): JsonResponse
    {
        $this->paymentService->deletePayment($payment);

        return response()->json(null, 204);
    }
}

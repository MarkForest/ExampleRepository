<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\V1\CreatePaymentData;
use App\DTO\Api\V1\CreatePaymentDTO;
use App\Exceptions\Api\V1\InsufficientFundsException;
use App\Http\Controllers\AntiPattern\Controller;
use App\Http\Requests\Api\V1\Payment\PaymentStoreRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Api\V1\PaymentService;
use http\Client\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    const LIMIT = 1000;

    public function __construct(public PaymentService $paymentService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     * @throws InsufficientFundsException
     */
    public function store(CreatePaymentData $data): JsonResponse
    {
        if ($data->getAmount() < self::LIMIT) {
            throw new InsufficientFundsException('Недостатньо коштів');
        }

        dd($data->toArray());

        $payment = $this->paymentService->processPayment($data);

        return response()->json(new PaymentResource($payment));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        $this->paymentService->deletePayment($payment);
    }
}

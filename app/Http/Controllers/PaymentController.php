<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTO\Payment\CreatePaymentDTO;
use App\Http\Controllers\AntiPattern\Controller;
use App\Http\Requests\PaymentStoreRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    public function index(): View
    {
        $payments = Payment::all();

        return view('payment.index', compact('payments'));
    }

    public function showPaymentForm(): View
    {
        $users = User::all();

        return view('payment.create', compact('users'));
    }

    public function store(PaymentStoreRequest $request): JsonResponse
    {
        /** @var CreatePaymentDTO $createPaymentDTO */
        $createPaymentDTO = CreatePaymentDTO::fromArray($request->validated());

        $payment = $this->paymentService->processPayment($createPaymentDTO);

        return response()->json(new PaymentResource($payment));
    }
}

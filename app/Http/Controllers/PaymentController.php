<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AntiPattern\Controller;
use App\Http\Requests\PaymentStoreRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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

    public function store(PaymentStoreRequest $request): RedirectResponse
    {
        $this->paymentService->processPayment($request->validated());

        return redirect()->route('payment.index')->with('success', 'Платіж успішно створено');
    }
}

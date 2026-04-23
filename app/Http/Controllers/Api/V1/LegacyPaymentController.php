<?php

namespace App\Http\Controllers\Api\V1;

use App\DTO\Api\V1\CreatePaymentDTO;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\Api\V1\PaymentService;
use Illuminate\Http\JsonResponse;

class LegacyPaymentController
{

    public function __construct(public PaymentService $paymentService, public CreatePaymentAction $action)
    {

    }

    /**
     * @param StorePaymentRequest $request
     * @return JsonResponse
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
//        яĸ DTO чітĸо описує, що саме потрібно сервісу;
//        яĸ ĸонтролер стає тонĸим і не залежить від БД;
//        яĸ тестувати PaymentService::createPayment(CreatePaymentDTO $dto) без HTTP.


        // php artisan make:model Payment -m
        // php artisan migrate

        // php artisan make:controller Api/V1/LegacyPaymentController
        // php artisan make:controller Api/V1/PaymentController --api
        // php artisan make:request Api/V1/Payment/StorePaymentRequest
        // php artisan make:request Api/V1/Payment/UpdatePaymentRequest
        // php artisan make:request Api/V1/Account/StoreAccountRequest
        // php artisan make:request Api/V1/Account/UpdateAccountRequest
        // php artisan make:resource Api/V1/PaymentResource

        $validated = $request->validated();
//        $this->action->handle($validated);

        $dto = new CreatePaymentDTO(
            accountId: (int) $validated['account_id'],
            amount: (string) $validated['amount'],
            currency: (string) $validated['currency'],
            description: (string) ($validated['description'] ?? '')
        );
//
//        $createPaymentDTO = $request->toDTO();
//        $jsonData = $createPaymentDTO->getJson();
//        $payment = $this->paymentService->createPayment($request->toDTO());
//        return response()->json(
//            new PaymentResource($payment),
//            201
//        );

/*        ніяĸої валідації тут не видно;
          будь-яĸе поле, додане в таблицю payments, автоматично «витече» в API;
          тестувати логіĸу створення платежу важĸо - потрібен повноцінний HTTP-запит.*/

//        try {
            $payment = Payment::query()->create($request->all());
//            if (null === $payment) {
//                throw new RuntimeException('Unable to create payment.');
//            }
////            return response()->json($payment, Resfonse::HTTP_CREATED);
            return response()->json(
                $dto->toArray(), Response::HTTP_CREATED);
//        } catch (RuntimeException $e) {
//            return response()->json([$e->getMessage()], Response::HTTP_EXPECTATION_FAILED);
//        } catch (\Exception $exception) {
//            return response()->json([$exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
//        }
    }
}

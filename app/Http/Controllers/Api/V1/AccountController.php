<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTO\Api\V1\CreateAccountDTO;
use App\Http\Controllers\AntiPattern\Controller;
use App\Http\Requests\Api\V1\Account\AccountStoreRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\Api\V1\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    public function index(): AnonymousResourceCollection
    {
        $accounts = $this->accountService->paginateAccounts(20);

        return AccountResource::collection($accounts);
    }

    public function store(AccountStoreRequest $request): JsonResponse
    {
        $dto = CreateAccountDTO::fromArray($request->validated());
        $account = $this->accountService->createAccount($dto);

        return response()->json(['data' => new AccountResource($account)], 201);
    }

    public function show(int $id): JsonResponse
    {
        $account = $this->accountService->findById($id);

        if ($account === null) {
            abort(404);
        }

        return response()->json(['data' => new AccountResource($account)]);
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->accountService->deleteAccount($account);

        return response()->json(null, 204);
    }
}

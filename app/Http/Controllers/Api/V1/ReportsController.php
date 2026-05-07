<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\AntiPattern\Controller;
use App\Jobs\ExportAccountStatementJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ReportsController extends Controller
{
    public function generateAccountStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'from'       => ['required', 'date'],
            'to'         => ['required', 'date', 'after_or_equal:from'],
        ]);

        $taskId = (string) Str::uuid();

        ExportAccountStatementJob::dispatch(
            (int) $validated['account_id'],
            (string) $validated['from'],
            (string) $validated['to'],
            $taskId,
        );

        return response()->json([
            'status'  => 'accepted',
            'task_id' => $taskId,
            'message' => 'Report generation started.',
        ], 202);
    }
}

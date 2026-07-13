<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiDashboard\AiDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        AiDashboardService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'strategy_profile_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'instrument_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:200',
            ],
        ]);

        return response()->json(
            $service->overview(
                strategyProfileId:
                    $validated['strategy_profile_id'] ?? null,
                instrumentId:
                    $validated['instrument_id'] ?? null,
                limit: $validated['limit'] ?? 50,
            )
        );
    }
}

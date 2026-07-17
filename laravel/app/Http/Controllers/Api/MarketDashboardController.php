<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketDashboardController extends Controller
{
    public function __construct(private readonly MarketDashboardService $service)
    {
    }

    public function latest(): JsonResponse
    {
        $snapshot = $this->service->latest();

        if (! $snapshot) {
            return response()->json([
                'data' => null,
                'message' => 'Noch kein Market Snapshot vorhanden.',
            ], 404);
        }

        return response()->json(['data' => $snapshot]);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 30);

        return response()->json([
            'data' => $this->service->history($limit),
        ]);
    }
}

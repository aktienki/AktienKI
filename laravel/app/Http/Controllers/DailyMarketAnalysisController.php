<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyMarketAnalysisController extends Controller
{
    public function __invoke(): View
    {
        $analysis = DB::table('daily_market_ai_analyses')
            ->orderByDesc('analysis_date')
            ->orderByDesc('id')
            ->first();

        foreach (['sector_analysis', 'opportunities', 'risks', 'watchlist'] as $field) {
            if ($analysis) {
                $decoded = json_decode((string) ($analysis->{$field} ?? ''), true);
                $analysis->{$field} = is_array($decoded) ? $decoded : [];
            }
        }

        return view('daily-market-analysis', compact('analysis'));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarketAssessmentController extends Controller
{
    public function __invoke(): View
    {
        $analysis = DB::table('daily_market_ai_analyses')
            ->orderByDesc('analysis_date')
            ->orderByDesc('id')
            ->first();

        return view('market-assessment', compact('analysis'));
    }
}

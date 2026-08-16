<?php

namespace App\Http\Controllers;

use App\Services\YahooIndexService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BacktestTradePerformanceController extends SignalTransitionController
{
    public function index(Request $request, YahooIndexService $yahooIndexService, bool $backtestReference = true): View
    {
        return parent::index($request, $yahooIndexService, true);
    }
}

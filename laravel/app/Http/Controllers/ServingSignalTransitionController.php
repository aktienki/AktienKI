<?php

namespace App\Http\Controllers;

use App\Services\ServingReadService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

final class ServingSignalTransitionController extends Controller
{
    public function __invoke(Request $request, ServingReadService $serving): View
    {
        $allRows = $serving->signalTransitions();
        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $target = strtoupper(trim((string) $request->query('signal', '')));
        $rows = $allRows->filter(function (object $row) use ($search, $target): bool {
            if ($search !== '' && ! str_contains(mb_strtolower($row->name.' '.$row->symbol), $search)) return false;
            if ($target !== '' && $row->to_signal !== $target) return false;

            return true;
        })->values();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 100;
        $transitions = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $summary = (object) [
            'total' => $allRows->count(),
            'buy' => $allRows->where('to_signal', 'BUY')->count(),
            'watch' => $allRows->whereIn('to_signal', ['WATCH', 'WAIT'])->count(),
            'hold' => $allRows->where('to_signal', 'HOLD')->count(),
            'sell' => $allRows->where('to_signal', 'SELL')->count(),
            'last_at' => $allRows->max('changed_at'),
        ];

        return view('predictions.serving-signal-history', compact('transitions', 'summary'));
    }
}

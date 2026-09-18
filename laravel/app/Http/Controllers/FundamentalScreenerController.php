<?php

namespace App\Http\Controllers;

use App\Services\EarningsQuarterCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class FundamentalScreenerController extends Controller
{
    public function __invoke(Request $request, EarningsQuarterCardService $cards): View
    {
        $query = DB::table('instruments')
            ->where('type', 'stock')->where('is_active', true)->whereNull('deleted_at');

        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('symbol', 'ilike', "%{$term}%")->orWhere('name', 'ilike', "%{$term}%"));
        }

        $instruments = $query->orderBy('symbol')->get(['id', 'symbol', 'name']);

        $selected = null;
        $requestedSymbol = strtoupper(trim((string) $request->query('symbol', '')));
        if ($requestedSymbol !== '') {
            $selected = $instruments->first(fn ($i) => strtoupper($i->symbol) === $requestedSymbol);
        }
        $selected ??= $instruments->first();

        $years = $selected ? $cards->forInstrument($selected->id) : [];

        return view('screener.fundamental', [
            'instruments' => $instruments,
            'selected' => $selected,
            'years' => $years,
            'searchTerm' => $term ?? '',
        ]);
    }
}

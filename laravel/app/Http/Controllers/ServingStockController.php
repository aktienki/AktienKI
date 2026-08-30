<?php

namespace App\Http\Controllers;

use App\Services\ServingReadService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ServingStockController extends Controller
{
    public function __invoke(Request $request, string $symbol, ServingReadService $serving): View
    {
        $stock = $serving->stock($symbol);
        abort_unless($stock, 404, __('Für diese Aktie gibt es in der neuen Remote-Datenbank kein aktives Modell.'));

        return view('stocks.serving-show', compact('stock'));
    }
}

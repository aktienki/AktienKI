<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class StockListController extends Controller
{
    public function index(): View
    {
        return view('stocks.index');
    }
}
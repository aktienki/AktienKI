<?php

namespace App\Http\Controllers;

use App\Services\EarningsCalendarService;
use Illuminate\View\View;

final class EarningsCalendarController extends Controller
{
    public function __invoke(EarningsCalendarService $calendar): View
    {
        return view('screener.calendar', $calendar->upcoming());
    }
}

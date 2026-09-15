<?php

namespace App\Http\Controllers;

use App\Models\CorporateEvent;
use App\Models\Watchlist;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * A standalone "upcoming news" page: real earnings dates for the tracked
 * stock universe, synced daily from Twelve Data by
 * TwelveDataCorporateEventImporter (see events:sync-twelve-data) into the
 * corporate_events table. That table already holds real, matched data -
 * this page is the first place in the app that actually shows it, grouped
 * by date, with a small badge for entries on the user's own watchlists.
 */
final class UpcomingEventsController extends Controller
{
    private const LOOKAHEAD_DAYS = 60;

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $watchedInstrumentIds = Watchlist::query()
            ->where('user_id', $user->id)
            ->with('items:id,watchlist_id,instrument_id')
            ->get()
            ->flatMap(fn (Watchlist $watchlist) => $watchlist->items->pluck('instrument_id'))
            ->unique()
            ->all();

        $events = CorporateEvent::query()
            ->with('instrument:id,symbol,name,country')
            ->where('event_type', 'earnings')
            ->whereBetween('event_date', [now()->toDateString(), now()->addDays(self::LOOKAHEAD_DAYS)->toDateString()])
            ->whereHas('instrument')
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->limit(200)
            ->get()
            ->filter(fn (CorporateEvent $event): bool => $event->instrument !== null)
            ->map(fn (CorporateEvent $event): array => [
                'date' => Carbon::parse($event->event_date)->toDateString(),
                'time' => $event->event_time,
                'symbol' => $event->instrument->symbol,
                'name' => $event->instrument->name ?: $event->instrument->symbol,
                'country' => $event->instrument->country,
                'epsEstimate' => $event->eps_estimate,
                'epsActual' => $event->eps_actual,
                'surprisePercent' => $event->surprise_percent,
                'isWatched' => in_array($event->instrument_id, $watchedInstrumentIds, true),
                'url' => route('stocks.show', ['symbol' => $event->instrument->symbol, 'return_to' => '/anstehende-news']),
            ]);

        $groupedByDate = $events->groupBy('date');

        return view('upcoming-events', [
            'groupedByDate' => $groupedByDate,
            'lookaheadDays' => self::LOOKAHEAD_DAYS,
        ]);
    }
}

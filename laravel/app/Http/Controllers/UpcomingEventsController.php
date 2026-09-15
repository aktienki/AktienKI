<?php

namespace App\Http\Controllers;

use App\Models\CorporateEvent;
use App\Models\Watchlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A standalone "upcoming news" page: real earnings dates for the tracked
 * stock universe, synced daily from Twelve Data by
 * TwelveDataCorporateEventImporter (see events:sync-twelve-data) into the
 * corporate_events table. That table already holds real, matched data -
 * this page is the first place in the app that actually shows it, grouped
 * by date, with a small badge for entries on the user's own watchlists.
 * upcomingEvents()/recentEvents() are public so the concept dashboard's
 * own "Anstehende News" tab can reuse the exact same lists.
 */
final class UpcomingEventsController extends Controller
{
    public const LOOKAHEAD_DAYS = 60;

    public const LOOKBACK_DAYS = 30;

    public function __invoke(Request $request): View
    {
        $upcoming = $this->upcomingEvents($request, self::LOOKAHEAD_DAYS, 200);
        $recent = $this->recentEvents($request, self::LOOKBACK_DAYS, 100);

        return view('upcoming-events', [
            'groupedByDate' => $upcoming->groupBy('date'),
            'recentGroupedByDate' => $recent->groupBy('date'),
            'lookaheadDays' => self::LOOKAHEAD_DAYS,
            'lookbackDays' => self::LOOKBACK_DAYS,
        ]);
    }

    /**
     * @return Collection<int, array{date: string, time: ?string, instrumentId: int, symbol: string, name: string, country: ?string, epsEstimate: ?float, epsActual: ?float, surprisePercent: ?float, isWatched: bool, url: string}>
     */
    public function upcomingEvents(Request $request, int $lookaheadDays = self::LOOKAHEAD_DAYS, int $limit = 200): Collection
    {
        return $this->events(
            $request,
            fn (Builder $query) => $query->whereBetween('event_date', [now()->toDateString(), now()->addDays($lookaheadDays)->toDateString()])
                ->orderBy('event_date')->orderBy('event_time'),
            $limit,
        );
    }

    /**
     * Already-reported earnings within the look-back window (real EPS
     * actual/surprise, not just the estimate) - newest first.
     *
     * @return Collection<int, array{date: string, time: ?string, instrumentId: int, symbol: string, name: string, country: ?string, epsEstimate: ?float, epsActual: ?float, surprisePercent: ?float, isWatched: bool, url: string}>
     */
    public function recentEvents(Request $request, int $daysBack = self::LOOKBACK_DAYS, int $limit = 100): Collection
    {
        return $this->events(
            $request,
            fn (Builder $query) => $query->whereBetween('event_date', [now()->subDays($daysBack)->toDateString(), now()->subDay()->toDateString()])
                ->orderByDesc('event_date')->orderBy('event_time'),
            $limit,
        );
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @return Collection<int, array{date: string, time: ?string, instrumentId: int, symbol: string, name: string, country: ?string, epsEstimate: ?float, epsActual: ?float, surprisePercent: ?float, isWatched: bool, url: string}>
     */
    private function events(Request $request, callable $scope, int $limit): Collection
    {
        $user = $request->user();

        $watchedInstrumentIds = Watchlist::query()
            ->where('user_id', $user->id)
            ->with('items:id,watchlist_id,instrument_id')
            ->get()
            ->flatMap(fn (Watchlist $watchlist) => $watchlist->items->pluck('instrument_id'))
            ->unique()
            ->all();

        $query = CorporateEvent::query()
            ->with('instrument:id,symbol,name,country')
            ->where('event_type', 'earnings')
            ->whereHas('instrument');

        return $scope($query)
            ->limit($limit)
            ->get()
            ->filter(fn (CorporateEvent $event): bool => $event->instrument !== null)
            ->map(fn (CorporateEvent $event): array => [
                'date' => Carbon::parse($event->event_date)->toDateString(),
                'time' => $event->event_time,
                'instrumentId' => $event->instrument_id,
                'symbol' => $event->instrument->symbol,
                'name' => $event->instrument->name ?: $event->instrument->symbol,
                'country' => $event->instrument->country,
                'epsEstimate' => $event->eps_estimate,
                'epsActual' => $event->eps_actual,
                'surprisePercent' => $event->surprise_percent,
                'isWatched' => in_array($event->instrument_id, $watchedInstrumentIds, true),
                'url' => route('stocks.show', ['symbol' => $event->instrument->symbol, 'return_to' => '/anstehende-news']),
            ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ServingMarketSnapshotService;
use App\Services\ServingScreenerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * A deliberately small, separate alternative to DashboardController's bento
 * grid - built to answer "what's the most important thing right now"
 * without the drag-and-drop customization, the dozen-plus conditional card
 * variants, or the fixed-height/overflow layout traps that caused several
 * real bugs on the original dashboard (donuts silently clipped, a "two
 * column" card that was single-column at every width, a card styled
 * inconsistently with its siblings). Four focused sections, each reusing
 * data the app already computes elsewhere rather than a new source of
 * truth: market situation, best/newest stocks, recent signal changes, and
 * upcoming reminders. Every section has an explicit, designed empty state
 * instead of leftover blank space.
 */
final class DashboardConceptController extends Controller
{
    public function __invoke(Request $request, ServingScreenerService $screener): View
    {
        $user = $request->user();

        $snapshot = app(ServingMarketSnapshotService::class)->snapshot();
        $market = $this->marketSituation($snapshot);

        $remoteRequest = Request::create('/screener', 'GET', ['limit' => 'all']);
        $remoteRequest->setUserResolver(fn () => $user);
        $stocks = collect($screener->data($remoteRequest)['stocks'] ?? []);

        $bestStocks = $this->bestStocks($stocks);
        $recentChanges = $this->recentSignalChanges($stocks);
        $reminders = $this->upcomingReminders($user->id);

        return view('dashboard-concept', [
            'market' => $market,
            'bestStocks' => $bestStocks,
            'recentChanges' => $recentChanges,
            'reminders' => $reminders,
        ]);
    }

    /** @return array<string, mixed> */
    private function marketSituation(array $snapshot): array
    {
        if (! ($snapshot['available'] ?? false)) {
            return ['available' => false];
        }

        $assessment = (array) ($snapshot['assessment'] ?? []);
        $analysis = (array) ($snapshot['analysis'] ?? []);
        $distribution = (array) ($snapshot['transition_stats']['distribution'] ?? []);
        $sectors = collect($analysis['sectors'] ?? []);

        return [
            'available' => true,
            'score' => is_numeric($assessment['score'] ?? null) ? (float) $assessment['score'] : null,
            'status' => (string) ($assessment['status'] ?? '—'),
            'tone' => (string) ($assessment['tone'] ?? 'neutral'),
            'summary' => (string) ($assessment['summary'] ?? ''),
            'riskLevel' => (string) ($analysis['riskLevel'] ?? '—'),
            'averageChange' => is_numeric($assessment['averageChange'] ?? null) ? (float) $assessment['averageChange'] : null,
            'calculationDate' => $snapshot['calculation_date'] ?? null,
            'strongestSector' => $sectors->sortByDesc('return')->first(),
            'weakestSector' => $sectors->sortBy('return')->first(),
            'distribution' => [
                'BUY' => (int) ($distribution['BUY'] ?? 0),
                'WATCH' => (int) ($distribution['WATCH'] ?? 0),
                'HOLD' => (int) ($distribution['HOLD'] ?? 0),
                'SELL' => (int) ($distribution['SELL'] ?? 0),
            ],
        ];
    }

    /**
     * Best (highest composite score) and newest (most recently activated)
     * BUY/WATCH stocks - deliberately NOT the main dashboard's "champion"
     * criteria (external confirmation + panel coverage), which is narrow
     * enough to sit empty most of the time. This should almost always have
     * something to show.
     *
     * @return array<string, Collection>
     */
    private function bestStocks(Collection $stocks): array
    {
        $eligible = $stocks->filter(fn (object $stock): bool => in_array(
            strtoupper((string) ($stock->personalized_signal ?? '')),
            ['BUY', 'WATCH'],
            true,
        ));

        $best = $eligible
            ->filter(fn (object $stock): bool => is_numeric($stock->composite_score ?? null))
            ->sortByDesc(fn (object $stock): float => (float) $stock->composite_score)
            ->take(5)
            ->values();

        $newest = $eligible
            ->filter(fn (object $stock): bool => filled($stock->signal_transition_at ?? null))
            ->sortByDesc(fn (object $stock): string => (string) $stock->signal_transition_at)
            ->take(5)
            ->values();

        return ['best' => $best, 'newest' => $newest];
    }

    /** Most recent signal transitions across the whole universe, any direction. */
    private function recentSignalChanges(Collection $stocks): Collection
    {
        return $stocks
            ->filter(fn (object $stock): bool => filled($stock->signal_transition_at ?? null))
            ->sortByDesc(fn (object $stock): string => (string) $stock->signal_transition_at)
            ->take(6)
            ->values();
    }

    /**
     * "Termine" means dated reminders specifically - prediction_purchase_
     * reminders is the only table with an actual date attached
     * (remind_on); entry_signal_alerts are standing watch-triggers with no
     * date, and there is no earnings-calendar table yet (earnings are
     * intentionally hidden app-wide until published to a serving table).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function upcomingReminders(int $userId): Collection
    {
        return DB::table('prediction_purchase_reminders as reminder')
            ->join('instruments as instrument', 'instrument.id', '=', 'reminder.instrument_id')
            ->where('reminder.user_id', $userId)
            ->where('reminder.status', 'active')
            ->whereDate('reminder.remind_on', '>=', today())
            ->orderBy('reminder.remind_on')
            ->get(['reminder.remind_on', 'reminder.intent', 'instrument.symbol', 'instrument.name'])
            ->map(fn (object $reminder): array => [
                'symbol' => $reminder->symbol,
                'name' => $reminder->name,
                'label' => $reminder->intent === 'purchased' ? __('SELL-Überwachung') : __('Positiv-Erinnerung'),
                'date' => Carbon::parse($reminder->remind_on)->startOfDay(),
            ])
            ->take(6)
            ->values();
    }
}

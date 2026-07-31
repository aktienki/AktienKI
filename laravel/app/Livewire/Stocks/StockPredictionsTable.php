<?php

namespace App\Livewire\Stocks;

use App\Services\PersonalizedSignalService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

final class StockPredictionsTable extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $country = '';

    #[Url(except: '')]
    public string $sector = '';

    #[Url(except: '')]
    public string $signal = '';

    #[Url(except: '')]
    public string $exchange = '';

    #[Url(as: 'score_min', except: '')]
    public string $minScore = '';

    #[Url(as: 'score_max', except: '')]
    public string $maxScore = '';

    #[Url(as: 'sort', except: 'prediction_score')]
    public string $sortField = 'prediction_score';

    #[Url(as: 'direction', except: 'desc')]
    public string $sortDirection = 'desc';

    public ?int $watchlistPickerInstrumentId = null;

    public array $comparisonSelection = [];

    public bool $comparisonLimitReached = false;

    public function mount(): void
    {
        if ($this->country === '' && request()->filled('country')) {
            $this->country = strtoupper((string) request()->query('country'));
        }

        if ($this->exchange === '' && request()->filled('exchange')) {
            $this->exchange = strtoupper(trim((string) request()->query('exchange')));
        }

        if ($this->signal === '' && request()->filled('signal')) {
            $signal = strtoupper(trim((string) request()->query('signal')));
            $this->signal = in_array($signal, ['SELL', 'HOLD', 'WATCH', 'BUY'], true) ? $signal : '';
        }
    }

    public function sortBy(string $field): void
    {
        if (! array_key_exists($field, $this->sortableColumns())) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

    }

    public function clearFilters(): void
    {
        $this->reset('search', 'country', 'sector', 'signal', 'exchange', 'minScore', 'maxScore');
    }

    public function toggleComparison(int $instrumentId): void
    {
        $selection = collect($this->comparisonSelection)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selection->contains($instrumentId)) {
            $this->comparisonSelection = $selection
                ->reject(fn ($id) => $id === $instrumentId)
                ->values()
                ->all();
            $this->comparisonLimitReached = false;

            return;
        }

        if ($selection->count() >= 5) {
            $this->comparisonLimitReached = true;

            return;
        }

        $exists = DB::table('instruments')
            ->where('id', $instrumentId)
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            $this->comparisonSelection = $selection->push($instrumentId)->values()->all();
            $this->comparisonLimitReached = false;
        }
    }

    public function compareSelected()
    {
        $ids = collect($this->comparisonSelection)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(5)
            ->values();

        if ($ids->count() < 2) {
            return redirect()->route('stocks.index');
        }

        return redirect()->route('stocks.compare', ['ids' => $ids->implode(',')]);
    }

    public function openWatchlistPicker(int $instrumentId): void
    {
        $instrumentExists = DB::table('instruments')
            ->where('id', $instrumentId)
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if ($instrumentExists) {
            $this->watchlistPickerInstrumentId = $instrumentId;
        }
    }

    public function closeWatchlistPicker(): void
    {
        $this->watchlistPickerInstrumentId = null;
    }

    public function toggleWatchlist(int $instrumentId, ?int $watchlistId = null): void
    {
        $watchlistQuery = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('active', true);

        if ($watchlistId !== null) {
            $watchlistQuery->where('id', $watchlistId);
        }

        $watchlistId = $watchlistQuery
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');

        if (! $watchlistId) {
            $this->watchlistPickerInstrumentId = null;

            return;
        }

        $instrumentExists = DB::table('instruments')
            ->where('id', $instrumentId)
            ->where('type', 'stock')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if (! $instrumentExists) {
            $this->watchlistPickerInstrumentId = null;

            return;
        }

        DB::transaction(function () use ($watchlistId, $instrumentId): void {
            $item = DB::table('watchlist_items')
                ->where('watchlist_id', $watchlistId)
                ->where('instrument_id', $instrumentId);

            if ($item->exists()) {
                $item->delete();

                return;
            }

            $entryPrediction = DB::table('predictions')
                ->where('instrument_id', $instrumentId)
                ->whereNotNull('current_price')
                ->orderByDesc('prediction_time')
                ->orderByDesc('id')
                ->first(['id', 'current_price']);
            $entryCurrency = DB::table('instruments')
                ->where('id', $instrumentId)
                ->value('currency');

            DB::table('watchlist_items')->insert([
                'watchlist_id' => $watchlistId,
                'instrument_id' => $instrumentId,
                'prediction_id' => $entryPrediction?->id,
                'added_at' => now(),
                'entry_price' => $entryPrediction?->current_price,
                'entry_price_at' => $entryPrediction !== null ? now() : null,
                'entry_currency' => $entryCurrency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->watchlistPickerInstrumentId = null;
    }

    public function render(): View
    {
        $signalSql = app(PersonalizedSignalService::class)->sql('prediction', auth()->user());
        $query = $this->baseQuery()
            ->when($this->search !== '', function (Builder $query) {
                $term = '%'.strtolower(trim($this->search)).'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw('LOWER(instrument.symbol) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(instrument.industry, ?)) LIKE ?', ['', $term]));
            })
            ->when($this->country !== '', fn (Builder $query) => $query->where('instrument.country', $this->country))
            ->when($this->sector !== '', fn (Builder $query) => $query->where('instrument.sector', $this->sector))
            ->when($this->exchange !== '', fn (Builder $query) => $query->where('exchange.code', $this->exchange))
            ->when($this->signal !== '', fn (Builder $query) =>
                $query->whereRaw("({$signalSql}) = ?", [$this->signal]))
            ->when($this->minScore !== '' && is_numeric($this->minScore), fn (Builder $query) =>
                $query->whereRaw(
                    '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END) >= ?',
                    [(float) $this->minScore],
                ))
            ->when($this->maxScore !== '' && is_numeric($this->maxScore), fn (Builder $query) =>
                $query->whereRaw(
                    '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END) <= ?',
                    [(float) $this->maxScore],
                ));

        $sortColumn = $this->sortableColumns()[$this->sortField] ?? 'prediction.prediction_score';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';
        $query->orderByRaw($sortColumn.' '.$direction.' NULLS LAST');

        $userWatchlists = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        $watchlistIds = $userWatchlists->pluck('id');
        $watchlistMemberships = $watchlistIds->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->whereIn('watchlist_id', $watchlistIds)
                ->get(['instrument_id', 'watchlist_id'])
                ->groupBy('instrument_id')
                ->map(fn ($items) => $items->pluck('watchlist_id')->map(fn ($id) => (int) $id)->all());
        $watchlistPickerInstrument = $this->watchlistPickerInstrumentId
            ? DB::table('instruments')
                ->where('id', $this->watchlistPickerInstrumentId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first(['id', 'symbol', 'name'])
            : null;

        return view('livewire.stocks.stock-predictions-table', [
            'rows' => $query->get(),
            'countries' => $this->filterOptions('country'),
            'sectors' => $this->filterOptions('sector'),
            'signals' => collect(['SELL', 'HOLD', 'WATCH', 'BUY']),
            'userWatchlists' => $userWatchlists,
            'watchlistMemberships' => $watchlistMemberships,
            'watchlistPickerInstrument' => $watchlistPickerInstrument,
        ]);
    }

    private function baseQuery(): Builder
    {
        $signalSql = app(PersonalizedSignalService::class)->sql('prediction', auth()->user());
        $latestPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');
        $latestQuotes = DB::table('current_stock_quotes')
            ->where('status', 'current')
            ->selectRaw('instrument_id, MAX(id) AS quote_id')
            ->groupBy('instrument_id');

        return DB::table('instruments as instrument')
            ->leftJoin('exchanges as exchange', 'exchange.id', '=', 'instrument.exchange_id')
            ->leftJoinSub($latestPredictions, 'latest', fn ($join) =>
                $join->on('latest.instrument_id', '=', 'instrument.id'))
            ->leftJoin('predictions as prediction', 'prediction.id', '=', 'latest.prediction_id')
            ->leftJoinSub($latestQuotes, 'latest_quote', fn ($join) =>
                $join->on('latest_quote.instrument_id', '=', 'instrument.id'))
            ->leftJoin('current_stock_quotes as current_quote', 'current_quote.id', '=', 'latest_quote.quote_id')
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->whereNull('instrument.deleted_at')
            ->select([
                'instrument.id', 'instrument.symbol', 'instrument.name', 'instrument.country',
                'instrument.sector', 'instrument.industry', 'instrument.currency',
                'prediction.predicted_price_5d',
                'prediction.prediction_score', 'prediction.confidence',
                'prediction.prediction_time',
            ])
            ->selectRaw('COALESCE(current_quote.price, prediction.current_price) AS current_price')
            ->selectRaw('COALESCE(prediction.risk_score, prediction.drawdown_risk_factor) AS risk_score')
            ->selectRaw("{$signalSql} AS signal")
            ->selectRaw('((prediction.predicted_price_5d - COALESCE(current_quote.price, prediction.current_price)) / NULLIF(COALESCE(current_quote.price, prediction.current_price), 0)) * 100 AS expected_return_5d');
    }

    private function sortableColumns(): array
    {
        return [
            'symbol' => 'instrument.symbol',
            'name' => 'instrument.name',
            'country' => 'instrument.country',
            'sector' => 'instrument.sector',
            'current_price' => 'COALESCE(current_quote.price, prediction.current_price)',
            'predicted_price_5d' => 'prediction.predicted_price_5d',
            'expected_return_5d' => 'expected_return_5d',
            'prediction_score' => '(CASE WHEN prediction.prediction_score <= 1 THEN prediction.prediction_score * 10 WHEN prediction.prediction_score <= 10 THEN prediction.prediction_score ELSE prediction.prediction_score / 10 END)',
            'confidence' => 'prediction.confidence',
            'risk_score' => 'COALESCE(prediction.risk_score, prediction.drawdown_risk_factor)',
            'signal' => app(PersonalizedSignalService::class)->sql('prediction', auth()->user()),
            'prediction_time' => 'prediction.prediction_time',
        ];
    }

    private function filterOptions(string $column)
    {
        return DB::table('instruments')
            ->where('type', 'stock')->where('is_active', true)->whereNull('deleted_at')
            ->whereNotNull($column)->where($column, '<>', '')
            ->distinct()->orderBy($column)->pluck($column);
    }
}

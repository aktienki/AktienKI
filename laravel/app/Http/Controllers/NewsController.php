<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $days = max(1, min(365, $request->integer('days', 30)));
        $minimumRelevance = max(0, min(100, $request->integer('relevance_min', 0)));
        $sentiment = in_array($request->string('sentiment')->toString(), ['positive', 'neutral', 'negative', 'unrated'], true)
            ? $request->string('sentiment')->toString()
            : '';
        $sort = in_array($request->string('sort')->toString(), ['published_at', 'relevance_score', 'sentiment_score', 'symbol', 'headline'], true)
            ? $request->string('sort')->toString()
            : 'published_at';
        $direction = $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $search = trim($request->string('q')->toString());

        $latestPredictionIds = DB::table('predictions as latest_source')
            ->join('instruments as ranked_instrument', 'ranked_instrument.id', '=', 'latest_source.instrument_id')
            ->where('ranked_instrument.type', 'stock')
            ->where('ranked_instrument.is_active', true)
            ->whereNull('ranked_instrument.deleted_at')
            ->groupBy('latest_source.instrument_id')
            ->selectRaw('latest_source.instrument_id, MAX(latest_source.id) AS prediction_id');
        $scoreSql = '(CASE '
            .'WHEN COALESCE(current_source.prediction_score, current_source.ai_score) IS NULL THEN NULL '
            .'WHEN COALESCE(current_source.prediction_score, current_source.ai_score) <= 1 THEN COALESCE(current_source.prediction_score, current_source.ai_score) * 10 '
            .'WHEN COALESCE(current_source.prediction_score, current_source.ai_score) <= 10 THEN COALESCE(current_source.prediction_score, current_source.ai_score) '
            .'ELSE COALESCE(current_source.prediction_score, current_source.ai_score) / 10 END)';
        $currentPredictions = DB::table('predictions as current_source')
            ->joinSub($latestPredictionIds, 'latest_prediction', fn ($join) => $join->on('latest_prediction.prediction_id', '=', 'current_source.id'))
            ->select('current_source.instrument_id', 'current_source.signal', 'current_source.confidence', 'current_source.current_price', 'current_source.predicted_price_20d')
            ->selectRaw("{$scoreSql} AS score_10");
        $rankedPredictions = DB::query()
            ->fromSub($currentPredictions, 'rank_source')
            ->select('rank_source.instrument_id', 'rank_source.signal', 'rank_source.score_10', 'rank_source.confidence', 'rank_source.current_price', 'rank_source.predicted_price_20d')
            ->selectRaw('DENSE_RANK() OVER (ORDER BY rank_source.score_10 DESC NULLS LAST) AS global_rank');

        $query = DB::table('news')
            ->join('instruments as instrument', 'instrument.id', '=', 'news.instrument_id')
            ->leftJoinSub($rankedPredictions, 'current_prediction', fn ($join) => $join->on('current_prediction.instrument_id', '=', 'instrument.id'))
            ->where('instrument.type', 'stock')
            ->where('instrument.is_active', true)
            ->where('instrument.is_german_tradeable', true)
            ->whereNull('instrument.deleted_at')
            ->where('news.published_at', '>=', now()->subDays($days))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $query) use ($needle): void {
                    $query->whereRaw('LOWER(instrument.symbol) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(instrument.name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(news.headline) LIKE ?', [$needle]);
                });
            })
            ->when($minimumRelevance > 0, fn (Builder $query) => $query->where('news.relevance_score', '>=', $minimumRelevance))
            ->when($sentiment === 'positive', fn (Builder $query) => $query->where('news.sentiment_score', '>=', .35))
            ->when($sentiment === 'negative', fn (Builder $query) => $query->where('news.sentiment_score', '<=', -.35))
            ->when($sentiment === 'neutral', fn (Builder $query) => $query->whereBetween('news.sentiment_score', [-.3499, .3499]))
            ->when($sentiment === 'unrated', fn (Builder $query) => $query->whereNull('news.sentiment_score'));

        $summary = (clone $query)->selectRaw(
            'COUNT(*) AS total, COUNT(DISTINCT news.instrument_id) AS stocks, '
            .'COUNT(*) FILTER (WHERE news.sentiment_score >= 0.35) AS positive, '
            .'COUNT(*) FILTER (WHERE news.sentiment_score <= -0.35) AS negative, '
            .'MAX(news.published_at) AS latest'
        )->first();

        $sortColumn = $sort === 'symbol' ? 'instrument.symbol' : ($sort === 'headline' ? 'news.headline' : 'news.'.$sort);
        $newsItems = $query
            ->select([
                'news.id', 'news.headline', 'news.body', 'news.summary', 'news.url', 'news.source',
                'news.provider', 'news.published_at', 'news.ai_summary_de', 'news.ai_summary_en',
                'news.sentiment_score', 'news.relevance_score', 'instrument.id as instrument_id', 'instrument.symbol', 'instrument.name',
                'current_prediction.score_10 as current_ai_score', 'current_prediction.global_rank',
                'current_prediction.signal as current_signal',
                'current_prediction.confidence as current_confidence', 'current_prediction.current_price',
                'current_prediction.predicted_price_20d',
            ])
            ->orderBy($sortColumn, $direction)
            ->orderByDesc('news.id')
            ->paginate(40)
            ->withQueryString();

        $userId = (int) $request->user()->id;
        $watchlistInstrumentIds = DB::table('watchlist_items as item')
            ->join('watchlists as list', 'list.id', '=', 'item.watchlist_id')
            ->where('list.user_id', $userId)->where('list.active', true)
            ->pluck('item.instrument_id')->map(fn ($id) => (int) $id)->flip();
        $portfolioInstrumentIds = DB::table('portfolio_positions as position')
            ->join('portfolios as portfolio', 'portfolio.id', '=', 'position.portfolio_id')
            ->where('portfolio.user_id', $userId)->where('portfolio.type', 'paper')->where('portfolio.active', true)
            ->pluck('position.instrument_id')->map(fn ($id) => (int) $id)->flip();
        $labels = DB::table('smart_selection_labels')->where('user_id', $userId)->where('is_active', true)->get(['name', 'criteria']);

        $newsItems->getCollection()->transform(function (object $item) use ($watchlistInstrumentIds, $portfolioInstrumentIds, $labels): object {
            $reasons = [];
            if ($watchlistInstrumentIds->has((int) $item->instrument_id)) $reasons[] = __('Watchlist');
            if ($portfolioInstrumentIds->has((int) $item->instrument_id)) $reasons[] = __('Musterdepot');
            $score = is_numeric($item->current_ai_score) ? (float) $item->current_ai_score : null;
            $confidence = is_numeric($item->current_confidence) ? (float) $item->current_confidence : null;
            if ($confidence !== null && $confidence <= 1) $confidence *= 100;
            $current = is_numeric($item->current_price) ? (float) $item->current_price : null;
            $target = is_numeric($item->predicted_price_20d) ? (float) $item->predicted_price_20d : null;
            $return = $current && $target ? (($target / $current) - 1) * 100 : null;
            foreach ($labels as $label) {
                $criteria = is_string($label->criteria) ? (json_decode($label->criteria, true) ?: []) : (array) $label->criteria;
                if (strtoupper((string) $item->current_signal) !== 'BUY') continue;
                if ($score === null || $score < (float) ($criteria['score_min'] ?? 0)) continue;
                if ($confidence === null || $confidence < (float) ($criteria['confidence_min'] ?? 0)) continue;
                if ($return === null || $return < (float) ($criteria['predicted_return_min'] ?? -20)) continue;
                $reasons[] = __('Label: :name', ['name' => $label->name]);
            }
            $item->personal_reasons = array_values(array_unique($reasons));
            $item->is_personal = $item->personal_reasons !== [];
            return $item;
        });

        return view('news.index', compact(
            'newsItems', 'summary', 'days', 'minimumRelevance', 'sentiment', 'sort', 'direction', 'search'
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\ChanceRiskScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KlaLandingController extends Controller
{
    protected string $symbol = 'KLAC';
    protected string $view = 'landing.kla';
    protected string $cachePrefix = 'kla';

    public function show(): View
    {
        $betaTesterLimit = 20;
        $betaTesterCount = Cache::remember('public.'.$this->cachePrefix.'.beta-count', now()->addMinutes(15), function () use ($betaTesterLimit): int {
            try {
                return min($betaTesterLimit,
                    DB::table('users')->where('account_status', 'tester')->count()
                    + DB::table('contact_messages as beta_request')
                        ->where('beta_request.meta->source', 'beta_request')
                        ->selectRaw('LOWER(beta_request.email)')->distinct()->count()
                );
            } catch (\Throwable) {
                return 0;
            }
        });

        return view($this->view, [
            'quote' => $this->latestQuote(),
            'score' => $this->latestScore(),
            'betaTesterCount' => $betaTesterCount,
            'betaTesterLimit' => $betaTesterLimit,
        ]);
    }

    public function quote(): JsonResponse
    {
        $quote = $this->latestQuote();

        return response()->json(['symbol' => $this->symbol, ...$quote], $quote['price'] === null ? 503 : 200);
    }

    /** @return array{price: ?float, currency: string, timestamp: ?string, realtime: bool, provider: string} */
    private function latestQuote(): array
    {
        return Cache::remember('public_'.$this->cachePrefix.'_landing_quote', now()->addSeconds(20), function (): array {
            $instrument = DB::table('instruments')
                ->where(fn ($query) => $query->whereRaw('UPPER(symbol) = ?', [$this->symbol])->orWhereRaw('UPPER(provider_symbol) = ?', [$this->symbol]))
                ->whereNull('deleted_at')->orderByDesc('is_active')->first(['id', 'symbol', 'currency']);

            if (! $instrument) return $this->emptyQuote();

            $stream = Cache::get('twelve_data_stream_quote_'.sha1(strtoupper((string) $instrument->symbol)));
            if (is_numeric($stream['price'] ?? null)) {
                return [
                    'price' => (float) $stream['price'],
                    'currency' => (string) (($stream['currency'] ?? null) ?: $instrument->currency ?: 'USD'),
                    'timestamp' => is_numeric($stream['timestamp'] ?? null) ? now()->setTimestamp((int) $stream['timestamp'])->toIso8601String() : now()->toIso8601String(),
                    'realtime' => true,
                    'provider' => 'TwelveData Stream',
                ];
            }

            $stored = DB::table('current_stock_quotes')->where('instrument_id', $instrument->id)->where('status', 'current')
                ->orderByDesc('quote_time')->orderByDesc('id')->first(['price', 'quote_time']);
            if (! $stored || ! is_numeric($stored->price)) return $this->emptyQuote();

            return [
                'price' => (float) $stored->price,
                'currency' => (string) ($instrument->currency ?: 'USD'),
                'timestamp' => $stored->quote_time ? (string) $stored->quote_time : null,
                'realtime' => false,
                'provider' => 'TwelveData · letzter Kurs',
            ];
        });
    }

    private function emptyQuote(): array
    {
        return ['price' => null, 'currency' => 'USD', 'timestamp' => null, 'realtime' => false, 'provider' => 'TwelveData'];
    }

    private function latestScore(): array
    {
        return Cache::remember('public_'.$this->cachePrefix.'_landing_score_v4', now()->addMinutes(5), function (): array {
            $instrumentId = DB::table('instruments')
                ->where(fn ($query) => $query->whereRaw('UPPER(symbol) = ?', [$this->symbol])->orWhereRaw('UPPER(provider_symbol) = ?', [$this->symbol]))
                ->whereNull('deleted_at')->orderByDesc('is_active')->value('id');
            if (! $instrumentId) return $this->withGrades(ChanceRiskScore::calculate(null, []));

            $rows = DB::table('predictions')->where('instrument_id', $instrumentId)
                ->orderByDesc('prediction_time')->orderByDesc('id')->limit(40)
                ->get(['prediction_horizon_minutes', 'current_price', 'predicted_price_5d', 'predicted_price_10d', 'predicted_price_15d', 'predicted_price_20d', 'ai_score', 'prediction_score', 'confidence', 'risk_score', 'drawdown_risk_factor']);

            $returns = [];
            foreach ([5 => 7200, 10 => 14400, 15 => 21600, 20 => 28800] as $days => $minutes) {
                $column = "predicted_price_{$days}d";
                $row = $rows->first(fn ($item) => (int) $item->prediction_horizon_minutes === $minutes && is_numeric($item->{$column}) && is_numeric($item->current_price) && (float) $item->current_price > 0);
                if ($row) $returns[$days] = (((float) $row->{$column} / (float) $row->current_price) - 1) * 100;
            }

            $latest = $rows->first();

            return $this->withGrades(ChanceRiskScore::calculate(
                $latest?->ai_score ?? $latest?->prediction_score,
                $returns,
                $latest?->confidence,
                $latest?->risk_score ?? $latest?->drawdown_risk_factor,
            ));
        });
    }

    private function withGrades(array $score): array
    {
        $score['chance_grade'] = ChanceRiskScore::grade((float) $score['chance']);
        $score['risk_grade'] = ChanceRiskScore::equityRiskGrade((float) $score['risk']);
        $score['chance_label'] = ChanceRiskScore::chanceLabel((float) $score['chance']);
        $score['risk_label'] = ChanceRiskScore::equityRiskLabel((float) $score['risk']);

        return $score;
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\PlanLevel;
use App\Services\PlanAccessService;
use App\Services\MarketDataEntitlementService;
use App\Services\PersonalizedSignalService;
use App\Services\TwelveDataService;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class StockController extends Controller
{
    public function report(Request $request, string $symbol): Response
    {
        $instrument = $this->instrument($symbol);
        $prediction = DB::table('predictions')->where('instrument_id', $instrument->id)
            ->when($request->integer('prediction') > 0, fn ($query) => $query->where('id', $request->integer('prediction')))
            ->orderByDesc('prediction_time')->orderByDesc('id')->first();
        $fundamental = DB::table('instrument_fundamentals')->where('instrument_id', $instrument->id)
            ->orderByDesc('snapshot_date')->orderByDesc('id')->first();
        $fundamentals = array_replace($this->decodeJson($fundamental?->data), array_filter([
            'marketCap' => $fundamental?->market_cap, 'trailingPE' => $fundamental?->trailing_pe,
            'forwardPE' => $fundamental?->forward_pe, 'priceToBook' => $fundamental?->price_to_book,
            'dividendYield' => $fundamental?->dividend_yield, 'profitMargins' => $fundamental?->profit_margin,
            'operatingMargins' => $fundamental?->operating_margin, 'returnOnEquity' => $fundamental?->return_on_equity,
            'revenue' => $fundamental?->revenue, 'revenueGrowth' => $fundamental?->revenue_growth,
            'ebitda' => $fundamental?->ebitda, 'totalCash' => $fundamental?->total_cash,
            'totalDebt' => $fundamental?->total_debt, 'operatingCashflow' => $fundamental?->operating_cash_flow,
            'freeCashflow' => $fundamental?->free_cash_flow,
        ], fn ($value) => $value !== null));
        $assessment = DB::table('stock_ai_assessments')->where('instrument_id', $instrument->id)
            ->when($prediction, fn ($query) => $query->where('prediction_id', $prediction->id))
            ->orderByDesc('assessment_date')->orderByDesc('id')->first();
        $trainedModel = $prediction?->trained_model_id
            ? DB::table('trained_models as tm')->leftJoin('model_quality_rankings as mq', 'mq.trained_model_id', '=', 'tm.id')
                ->leftJoin('model_definitions as md', 'md.id', '=', 'tm.model_definition_id')
                ->where('tm.id', $prediction->trained_model_id)->orderByDesc('mq.id')
                ->first([
                    'md.public_alias as model_alias', 'mq.quality_score',
                    'mq.direction_accuracy as hit_rate', 'mq.profit_factor',
                    'mq.maximum_drawdown as max_drawdown', 'tm.trained_at',
                    DB::raw("tm.metrics->>'stability' AS model_stability"),
                ])
            : null;
        $latestWalkForwardRunIds = DB::table('walk_forward_backtest_runs as wf_run')
            ->join('walk_forward_backtest_trades as wf_trade', 'wf_trade.run_id', '=', 'wf_run.id')
            ->where('wf_run.status', 'completed')
            ->where('wf_trade.instrument_id', $instrument->id)
            ->whereIn('wf_run.horizon_days', [5, 10, 15, 20])
            ->groupBy('wf_run.horizon_days')
            ->selectRaw('MAX(wf_run.id) AS id')
            ->pluck('id');
        $walkForwardStats = $latestWalkForwardRunIds->isEmpty() ? null : DB::table('walk_forward_backtest_trades')
            ->where('instrument_id', $instrument->id)->whereIn('run_id', $latestWalkForwardRunIds)
            ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('AVG(net_return) * 100 AS average_profit_per_trade_percent')->first();
        $patterns = $this->chartPatternStatistics((int) $instrument->id);
        $indicators = $this->indicatorCards($instrument);
        $horizonTargets = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($instrument, $prediction): array {
            $column = "predicted_price_{$days}d";
            $value = is_numeric($prediction?->{$column} ?? null) ? (float) $prediction->{$column} : null;
            if ($value === null) {
                $value = DB::table('predictions')->where('instrument_id', $instrument->id)
                    ->where('prediction_horizon_minutes', $days * 1440)->whereNotNull($column)
                    ->when($prediction?->prediction_time, fn ($query) => $query->where('prediction_time', '<=', $prediction->prediction_time))
                    ->orderByDesc('prediction_time')->orderByDesc('id')->value($column);
            }
            return [$days => is_numeric($value) ? (float) $value : null];
        })->all();
        $chartBars = $this->dailyBars((int) $instrument->id)->take(-120)->values();
        $chart = $this->stockReportChart($chartBars, $horizonTargets);
        $logoPath = public_path('brand/generated/bull-logo-light-clean.png');
        $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null;
        $toPercent = static fn ($value): ?float => is_numeric($value)
            ? max(0, min(100, (float) $value * ((float) $value <= 1 ? 100 : 1))) : null;
        $reportDonuts = [
            ['label' => 'KI-Score', 'value' => \App\Support\AiScore::toPercent($prediction?->prediction_score), 'display' => is_numeric(\App\Support\AiScore::toPercent($prediction?->prediction_score)) ? number_format((float) \App\Support\AiScore::toPercent($prediction?->prediction_score), 0, ',', '.') : '—', 'reverse' => false],
            ['label' => 'Konf.', 'value' => $toPercent($prediction?->confidence), 'display' => $toPercent($prediction?->confidence) !== null ? number_format($toPercent($prediction?->confidence), 0, ',', '.').'%' : '—', 'reverse' => false],
            ['label' => 'Hit-Rate', 'value' => is_numeric($walkForwardStats?->hit_rate) ? (float) $walkForwardStats->hit_rate : null, 'display' => is_numeric($walkForwardStats?->hit_rate) ? number_format((float) $walkForwardStats->hit_rate, 0, ',', '.').'%' : '—', 'reverse' => false],
            ['label' => 'Ø/Trade', 'value' => is_numeric($walkForwardStats?->average_profit_per_trade_percent) ? max(0, min(100, 50 + ((float) $walkForwardStats->average_profit_per_trade_percent * 25))) : null, 'display' => is_numeric($walkForwardStats?->average_profit_per_trade_percent) ? (((float) $walkForwardStats->average_profit_per_trade_percent > 0 ? '+' : '').number_format((float) $walkForwardStats->average_profit_per_trade_percent, 2, ',', '.').'%') : '—', 'reverse' => false],
            ['label' => 'Stabilität', 'value' => $toPercent($prediction?->horizon_fusion_stability_score ?? $trainedModel?->model_stability), 'display' => $toPercent($prediction?->horizon_fusion_stability_score ?? $trainedModel?->model_stability) !== null ? number_format($toPercent($prediction?->horizon_fusion_stability_score ?? $trainedModel?->model_stability), 0, ',', '.').'%' : '—', 'reverse' => false],
            ['label' => 'Risiko', 'value' => \App\Support\RiskScore::toPercent($prediction?->risk_score, $prediction?->drawdown_risk_factor, $trainedModel?->max_drawdown), 'display' => \App\Support\RiskScore::toPercent($prediction?->risk_score, $prediction?->drawdown_risk_factor, $trainedModel?->max_drawdown) !== null ? number_format(\App\Support\RiskScore::toPercent($prediction?->risk_score, $prediction?->drawdown_risk_factor, $trainedModel?->max_drawdown), 0, ',', '.').'%' : '—', 'reverse' => true],
        ];
        $reportDonuts = collect($reportDonuts)->map(function (array $donut): array {
            $donut['image'] = $this->stockReportDonut($donut);
            return $donut;
        })->all();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('stocks.report', compact('instrument', 'prediction', 'fundamental', 'fundamentals', 'assessment', 'trainedModel', 'patterns', 'indicators', 'horizonTargets', 'chart', 'logoData', 'reportDonuts'))->render(), 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();
        $filename = 'Aktienbericht-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $instrument->symbol).'-'.now()->format('Y-m-d').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache', 'Expires' => '0',
            'X-Stock-Symbol' => $instrument->symbol,
        ]);
    }

    private function stockReportChart($bars, array $targets): ?string
    {
        $closes = collect($bars)->map(fn ($bar) => (float) $bar->close)->filter(fn ($value) => $value > 0)->values();
        if ($closes->count() < 2) return null;
        $forecast = collect([5, 10, 15, 20])->map(fn ($day) => $targets[$day] ?? null)->filter(fn ($value) => is_numeric($value));
        $all = $closes->concat($forecast)->values(); $min = (float) $all->min(); $max = (float) $all->max();
        $pad = max(($max - $min) * .12, $max * .015); $min -= $pad; $max += $pad; $range = max(.00001, $max - $min);
        $w = 720; $h = 190; $left = 38; $right = 18; $top = 16; $bottom = 28; $plotW = $w-$left-$right; $plotH = $h-$top-$bottom;
        $hx = fn ($i) => $left + ($i / max(1, $closes->count()-1)) * ($plotW * .78);
        $fy = fn ($value) => $top + (($max-(float)$value)/$range)*$plotH;
        $history = $closes->map(fn ($value,$i) => number_format($hx($i),1,'.','').','.number_format($fy($value),1,'.',''))->implode(' ');
        $lastX = $hx($closes->count()-1); $last = (float) $closes->last(); $forecastPoints = [number_format($lastX,1,'.','').','.number_format($fy($last),1,'.','')];
        $labels = ''; foreach ([5,10,15,20] as $i => $day) { if (!is_numeric($targets[$day] ?? null)) continue; $x=$lastX+(($i+1)/4)*($plotW*.22); $y=$fy($targets[$day]); $forecastPoints[]=number_format($x,1,'.','').','.number_format($y,1,'.',''); $labels.='<circle cx="'.$x.'" cy="'.$y.'" r="3" fill="#22d3ee"/><text x="'.$x.'" y="'.($y-7).'" text-anchor="middle" font-size="8" fill="#0e7490" font-weight="bold">'.$day.'T</text>'; }
        $grid=''; for($i=0;$i<4;$i++){ $y=$top+($i/3)*$plotH; $value=$max-($i/3)*$range; $grid.='<line x1="'.$left.'" y1="'.$y.'" x2="'.($w-$right).'" y2="'.$y.'" stroke="#cbd5e1" stroke-width=".6"/><text x="'.($left-5).'" y="'.($y+3).'" text-anchor="end" font-size="7" fill="#64748b">'.number_format($value,0,',','.').'</text>'; }
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><rect width="100%" height="100%" rx="8" fill="#f8fafc"/>'.$grid.'<polyline points="'.$history.'" fill="none" stroke="#0891b2" stroke-width="2"/><line x1="'.$lastX.'" y1="'.$top.'" x2="'.$lastX.'" y2="'.($h-$bottom).'" stroke="#f59e0b" stroke-dasharray="4 3"/><polyline points="'.implode(' ',$forecastPoints).'" fill="none" stroke="#22c55e" stroke-width="2.2"/>'.$labels.'<text x="'.$left.'" y="'.($h-8).'" font-size="8" fill="#64748b">Historischer Kurs</text><text x="'.($w-$right).'" y="'.($h-8).'" text-anchor="end" font-size="8" fill="#16a34a">Prognose 5/10/15/20T</text></svg>';
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function stockReportDonut(array $donut): string
    {
        $available = is_numeric($donut['value'] ?? null);
        $value = $available ? max(0, min(100, (float) $donut['value'])) : 0;
        $quality = ! empty($donut['reverse']) ? 100 - $value : $value;
        $color = match (true) {
            ! $available => '#64748b', $quality >= 85 => '#22c55e', $quality >= 70 => '#84cc16',
            $quality >= 55 => '#d9e021', $quality >= 40 => '#facc15', $quality >= 25 => '#fb923c', default => '#fb7185',
        };
        $size = 160; $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true); imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        $hex = fn (string $hex): array => sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        [$r, $g, $b] = $hex($color); $activeColor = imagecolorallocate($image, $r, $g, $b);
        $track = imagecolorallocate($image, 51, 65, 85); $fill = imagecolorallocate($image, 30, 41, 59);
        imagefilledellipse($image, 80, 80, 126, 126, $track); imagefilledellipse($image, 80, 80, 96, 96, $fill);
        if ($available) {
            imagesetthickness($image, 15); imagearc($image, 80, 80, 111, 111, -90, -90 + (int) round(360 * $value / 100), $activeColor);
        }
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        $bold = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $text = (string) ($donut['display'] ?? '—'); $label = (string) ($donut['label'] ?? '');
        $center = static function ($image, string $font, float $size, int $y, string $text, int $color): void {
            $box = imagettfbbox($size, 0, $font, $text); $width = $box[2] - $box[0];
            imagettftext($image, $size, 0, (int) ((160 - $width) / 2), $y, $color, $font, $text);
        };
        $center($image, $bold, 19, 78, $text, $activeColor);
        $center($image, $font, 10, 103, $label, imagecolorallocate($image, 189, 202, 219));
        ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode((string) $png);
    }

    public function chartAnalysis(string $symbol): View
    {
        $instrument = $this->instrument($symbol);
        $exchange = $instrument->exchange_id
            ? DB::table('exchanges')->where('id', $instrument->exchange_id)->first()
            : null;
        $indicatorCards = $this->indicatorCards($instrument);

        return view('stocks.chart-analysis', compact(
            'instrument', 'exchange', 'indicatorCards'
        ));
    }

    public function show(
        Request $request,
        string $symbol,
        PersonalizedSignalService $personalizedSignals,
        PlanAccessService $planAccess,
        TwelveDataService $yahooFinance,
        MarketDataEntitlementService $marketDataEntitlements,
    ): View
    {
        $instrument = $this->instrument($symbol);
        $canViewRealtime = $planAccess->allowsTariff($request->user(), PlanLevel::Pro);
        $canUseChartIndicators = $planAccess->allowsTariff($request->user(), PlanLevel::Plus);
        $canViewChartPatterns = $canViewRealtime;
        $canUseChartZoom = $canViewRealtime;
        $marketSession = $this->marketSession($instrument);
        $historicalChartAllowed = $marketDataEntitlements->historicalChartsAllowed($instrument);
        $historicalChartRestrictionReason = $marketDataEntitlements->historicalChartRestrictionReason($instrument);

        $signalSql = $personalizedSignals->sql('prediction', auth()->user());
        $requestedPredictionId = $request->integer('prediction');
        $predictionQuery = DB::table('predictions as prediction')
            ->where('prediction.instrument_id', $instrument->id)
            ->select('prediction.*')
            ->selectRaw("{$signalSql} AS personalized_signal");

        if ($requestedPredictionId > 0) {
            $predictionQuery->where('prediction.id', $requestedPredictionId);
        } else {
            $predictionQuery
                ->orderByDesc('prediction.prediction_time')
                ->orderByDesc('prediction.id');
        }

        $prediction = $predictionQuery->first();
        abort_if($requestedPredictionId > 0 && ! $prediction, 404);
        if ($prediction && $requestedPredictionId === 0) {
            $currentQuote = DB::table('current_stock_quotes')
                ->where('instrument_id', $instrument->id)
                ->where('status', 'current')
                ->orderByDesc('quote_time')
                ->orderByDesc('id')
                ->first(['price', 'quote_time']);
            if ($currentQuote && is_numeric($currentQuote->price)) {
                $prediction->current_price = (float) $currentQuote->price;
                $prediction->current_quote_time = $currentQuote->quote_time;
            }
        }
        $horizonTargets = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($instrument, $prediction, $requestedPredictionId): array {
            $column = "predicted_price_{$days}d";
            $target = is_numeric($prediction?->{$column} ?? null) ? (float) $prediction->{$column} : null;
            if ($target === null) {
                $query = DB::table('predictions')
                    ->where('instrument_id', $instrument->id)
                    ->where('prediction_horizon_minutes', $days * 1440)
                    ->whereNotNull($column);
                if ($requestedPredictionId > 0 && $prediction?->prediction_time) {
                    $query->where('prediction_time', '<=', $prediction->prediction_time);
                }
                $target = $query->orderByDesc('prediction_time')->orderByDesc('id')->value($column);
                $target = is_numeric($target) ? (float) $target : null;
            }
            $current = is_numeric($prediction?->current_price ?? null) ? (float) $prediction->current_price : null;
            $return = $target !== null && $current !== null && $current !== 0.0
                ? (($target - $current) / $current) * 100
                : null;

            return [$days => ['price' => $target, 'return' => $return]];
        })->all();
        if ($canViewRealtime && $prediction && in_array(strtoupper((string) ($prediction->personalized_signal ?? 'HOLD')), ['BUY', 'WATCH'], true)) {
            $shortPullback = collect([5, 10])->contains(fn (int $days): bool =>
                is_numeric(data_get($horizonTargets, $days.'.return'))
                && (float) data_get($horizonTargets, $days.'.return') < 0
            );
            $longReturn = data_get($horizonTargets, '20.return');
            if ($shortPullback && is_numeric($longReturn) && (float) $longReturn >= 1.0) {
                $prediction->personalized_signal = 'WAIT';
            }
        }
        $horizonStability = collect([5, 10, 15, 20])->mapWithKeys(function (int $days) use ($instrument, $prediction, $requestedPredictionId, $horizonTargets): array {
            $query = DB::table('predictions')
                ->where('instrument_id', $instrument->id)
                ->where('prediction_horizon_minutes', $days * 1440);
            if ($requestedPredictionId > 0 && $prediction?->prediction_time) {
                $query->where('prediction_time', '<=', $prediction->prediction_time);
            }
            $row = $query->orderByDesc('prediction_time')->orderByDesc('id')->first([
                'horizon_fusion_stability_score', 'horizon_fusion_direction_consistency',
                'horizon_fusion_dispersion', 'horizon_fusion_noise_passed',
                'horizon_fusion_stability_passed',
            ]);
            $return = data_get($horizonTargets, $days.'.return');

            return [$days => [
                'price' => data_get($horizonTargets, $days.'.price'),
                'return' => $return,
                'direction' => ! is_numeric($return) ? null : ((float) $return > 0 ? 'up' : ((float) $return < 0 ? 'down' : 'flat')),
                'stability_score' => is_numeric($row?->horizon_fusion_stability_score) ? (float) $row->horizon_fusion_stability_score : null,
                'direction_consistency' => is_numeric($row?->horizon_fusion_direction_consistency) ? (float) $row->horizon_fusion_direction_consistency : null,
                'dispersion' => is_numeric($row?->horizon_fusion_dispersion) ? (float) $row->horizon_fusion_dispersion : null,
                'noise_passed' => $row?->horizon_fusion_noise_passed,
                'stability_passed' => $row?->horizon_fusion_stability_passed,
            ]];
        })->all();
        $signalChangedAt = null;
        if ($requestedPredictionId > 0 && $prediction?->prediction_time) {
            $signalHistory = DB::table('predictions as prediction')
                ->where('prediction.instrument_id', $instrument->id)
                ->where(function ($query) use ($prediction): void {
                    $query
                        ->where('prediction.prediction_time', '<', $prediction->prediction_time)
                        ->orWhere(function ($sameTime) use ($prediction): void {
                            $sameTime
                                ->where('prediction.prediction_time', '=', $prediction->prediction_time)
                                ->where('prediction.id', '<=', $prediction->id);
                        });
                })
                ->select(['prediction.id', 'prediction.prediction_time'])
                ->selectRaw("{$signalSql} AS personalized_signal")
                ->orderByDesc('prediction.prediction_time')
                ->orderByDesc('prediction.id')
                ->limit(2000)
                ->get();

            $selectedSignal = strtoupper((string) ($prediction->personalized_signal ?: 'HOLD'));
            $phaseStartedAt = null;
            foreach ($signalHistory as $signalPoint) {
                if (strtoupper((string) ($signalPoint->personalized_signal ?: 'HOLD')) !== $selectedSignal) {
                    $signalChangedAt = $phaseStartedAt;
                    break;
                }

                $phaseStartedAt = CarbonImmutable::parse($signalPoint->prediction_time);
            }
        }
        $modelQuality = $prediction?->trained_model_id
            ? DB::table('trained_models as trained_model')
                ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
                ->leftJoin('model_quality_rankings as model_quality', function ($join): void {
                    $join->on('model_quality.trained_model_id', '=', 'trained_model.id')
                        ->whereRaw('model_quality.id = (
                            SELECT MAX(latest_model_quality.id)
                            FROM model_quality_rankings AS latest_model_quality
                            WHERE latest_model_quality.trained_model_id = trained_model.id
                        )');
                })
                ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
                ->where('trained_model.id', $prediction->trained_model_id)
                ->first([
                    'model_definition.public_alias as model_alias',
                    'trained_model.trained_at',
                    'model_quality.quality_score',
                    'model_quality.profit_factor',
                    'model_quality.sharpe',
                    'model_quality.direction_accuracy',
                    'model_quality.trade_count',
                    'model_quality.maximum_drawdown',
                    DB::raw("trained_model.metrics->>'stability' AS model_stability"),
                    'model_quality.eligible',
                    'quality_tier.code as tier_code',
                    'quality_tier.name as tier_name',
                ])
            : null;
        $latestWalkForwardRunIds = DB::table('walk_forward_backtest_runs as wf_run')
            ->join('walk_forward_backtest_trades as wf_trade', 'wf_trade.run_id', '=', 'wf_run.id')
            ->where('wf_run.status', 'completed')
            ->where('wf_trade.instrument_id', $instrument->id)
            ->whereIn('wf_run.horizon_days', [5, 10, 15, 20])
            ->groupBy('wf_run.horizon_days')
            ->selectRaw('MAX(wf_run.id) AS id')
            ->pluck('id');
        $detailWalkForwardStats = $latestWalkForwardRunIds->isEmpty()
            ? null
            : DB::table('walk_forward_backtest_trades')
                ->where('instrument_id', $instrument->id)
                ->whereIn('run_id', $latestWalkForwardRunIds)
                ->selectRaw('COUNT(*) AS trade_count')
                ->selectRaw('AVG(CASE WHEN net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
                ->selectRaw('AVG(net_return) * 100 AS average_profit_per_trade_percent')
                ->first();
        $qualityGateTier = DB::table('model_quality_tiers')
            ->where('code', 'test')
            ->where('enabled', true)
            ->first();
        $modelQualityGateReasons = collect();
        if ($modelQuality && $qualityGateTier && ! in_array($modelQuality->tier_code, ['test', 'solid', 'strong'], true)) {
            $qualityChecks = [
                [__('Qualitätsscore'), $modelQuality->quality_score, $qualityGateTier->minimum_quality_score, 'min', 100, '%'],
                [__('Profit-Faktor'), $modelQuality->profit_factor, $qualityGateTier->minimum_profit_factor, 'min', 1, ''],
                [__('Sharpe Ratio'), $modelQuality->sharpe, $qualityGateTier->minimum_sharpe, 'min', 1, ''],
                [__('Richtungsgenauigkeit'), $modelQuality->direction_accuracy, $qualityGateTier->minimum_direction_accuracy, 'min', 100, '%'],
                [__('Validierte Trades'), $modelQuality->trade_count, $qualityGateTier->minimum_trade_count, 'min', 1, ''],
                [__('Maximaler Drawdown'), $modelQuality->maximum_drawdown, $qualityGateTier->maximum_drawdown, 'max', 100, '%'],
            ];
            foreach ($qualityChecks as [$name, $actual, $threshold, $direction, $multiplier, $unit]) {
                if (! is_numeric($actual) || ! is_numeric($threshold)) {
                    continue;
                }
                $failed = $direction === 'min'
                    ? (float) $actual < (float) $threshold
                    : (float) $actual > (float) $threshold;
                if ($failed) {
                    $modelQualityGateReasons->push([
                        'name' => $name,
                        'actual' => (float) $actual * $multiplier,
                        'threshold' => (float) $threshold * $multiplier,
                        'direction' => $direction,
                        'unit' => $unit,
                    ]);
                }
            }
        }
        $modelChallenger = $prediction?->trained_model_id
            ? DB::table('model_challengers as challenger')
                ->join('trained_models as trained_model', 'trained_model.id', '=', 'challenger.trained_model_id')
                ->leftJoin('model_definitions as model_definition', 'model_definition.id', '=', 'trained_model.model_definition_id')
                ->leftJoin('model_quality_rankings as model_quality', function ($join): void {
                    $join->on('model_quality.trained_model_id', '=', 'trained_model.id')
                        ->whereRaw('model_quality.id = (
                            SELECT MAX(latest_model_quality.id)
                            FROM model_quality_rankings AS latest_model_quality
                            WHERE latest_model_quality.trained_model_id = trained_model.id
                        )');
                })
                ->leftJoin('model_quality_tiers as quality_tier', 'quality_tier.id', '=', 'model_quality.tier_id')
                ->where('challenger.champion_model_id', $prediction->trained_model_id)
                ->where(fn ($query) => $query
                    ->where('challenger.instrument_id', $instrument->id)
                    ->orWhereNull('challenger.instrument_id'))
                ->orderByRaw('CASE WHEN challenger.instrument_id = ? THEN 0 ELSE 1 END', [$instrument->id])
                ->orderByDesc('challenger.elo_rating')
                ->orderByDesc('challenger.id')
                ->first([
                    'challenger.status',
                    'challenger.elo_rating',
                    'model_definition.public_alias as model_alias',
                    'model_quality.quality_score',
                    'model_quality.eligible',
                    'quality_tier.code as tier_code',
                    'quality_tier.name as tier_name',
                ])
            : null;
        $aiAssessment = DB::table('stock_ai_assessments')
            ->where('instrument_id', $instrument->id)
            ->when($requestedPredictionId > 0, fn ($query) => $query->where('prediction_id', $requestedPredictionId))
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->first();
        $aiAssessmentOpportunities = $this->decodeJson($aiAssessment?->opportunities);
        $aiAssessmentRisks = $this->decodeJson($aiAssessment?->risks);
        $aiAssessmentFactors = $this->decodeJson($aiAssessment?->key_factors);
        $topStockAnalysis = DB::table('daily_top_stock_selections')
            ->where('instrument_id', $instrument->id)
            ->when($requestedPredictionId > 0, fn ($query) => $query->where('prediction_id', $requestedPredictionId))
            ->orderByDesc('selection_date')
            ->orderBy('rank')
            ->first();
        $topStockAnalysisDetails = $this->decodeJson($topStockAnalysis?->selection_details);
        $predictionFactorRatings = $this->decodeJson($prediction?->factor_ratings);
        $factorRatings = $predictionFactorRatings !== []
            ? $predictionFactorRatings
            : ($topStockAnalysisDetails['factor_ratings'] ?? []);
        $factorLabels = [
            'r2' => 'R²',
            'cagr' => __('Wachstum'),
            'error' => __('Prognosefehler'),
            'sharpe' => __('Sharpe Ratio'),
            'stability' => __('Stabilität'),
            'drawdown_risk' => __('Drawdown-Risiko'),
            'profit_factor' => __('Profit-Faktor'),
            'direction_accuracy' => __('Trefferquote Richtung'),
            'statistical_reliability' => __('Statistische Basis'),
        ];
        $topStockFactorRatings = collect($factorLabels)
            ->map(function (string $label, string $key) use ($factorRatings): array {
                $factor = is_array($factorRatings[$key] ?? null) ? $factorRatings[$key] : [];

                return [
                    'key' => $key,
                    'label' => $label,
                    'rating' => is_numeric($factor['rating'] ?? null) ? (int) $factor['rating'] : null,
                ];
            })
            ->values();
        $chartFocusAt = $requestedPredictionId > 0 && $prediction?->prediction_time
            ? CarbonImmutable::parse($prediction->prediction_time)
            : null;
        $returnTo = $request->query('return_to');
        $returnTo = is_string($returnTo)
            && Str::startsWith($returnTo, '/')
            && ! Str::startsWith($returnTo, '//')
                ? $returnTo
                : null;
        $returnLabel = $returnTo && Str::startsWith($returnTo, '/screener')
            ? __('Zurück zum Screener')
            : ($returnTo && Str::startsWith($returnTo, '/watchlists')
                ? __('Zurück zur Watchlist')
                : ($returnTo && Str::startsWith($returnTo, '/predictions')
                    ? __('Zurück zu Prognosen')
                    : ($returnTo && (Str::startsWith($returnTo, '/depots') || Str::startsWith($returnTo, '/paper-depots'))
                        ? __('Zurück zum Musterdepot')
                        : null)));

        $fundamental = DB::table('instrument_fundamentals')
            ->where('instrument_id', $instrument->id)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first();

        $fundamentalData = array_replace(
            $this->decodeJson($fundamental?->data),
            array_filter([
                'marketCap' => $fundamental?->market_cap,
                'enterpriseValue' => $fundamental?->enterprise_value,
                'trailingPE' => $fundamental?->trailing_pe,
                'forwardPE' => $fundamental?->forward_pe,
                'pegRatio' => $fundamental?->peg_ratio,
                'priceToBook' => $fundamental?->price_to_book,
                'priceToSalesTrailing12Months' => $fundamental?->price_to_sales,
                'dividendRate' => $fundamental?->dividend_rate,
                'dividendYield' => $fundamental?->dividend_yield,
                'payoutRatio' => $fundamental?->payout_ratio,
                'profitMargins' => $fundamental?->profit_margin,
                'operatingMargins' => $fundamental?->operating_margin,
                'returnOnAssets' => $fundamental?->return_on_assets,
                'returnOnEquity' => $fundamental?->return_on_equity,
                'totalRevenue' => $fundamental?->revenue,
                'revenueGrowth' => $fundamental?->revenue_growth,
                'grossProfits' => $fundamental?->gross_profit,
                'ebitda' => $fundamental?->ebitda,
                'netIncomeToCommon' => $fundamental?->net_income,
                'totalCash' => $fundamental?->total_cash,
                'totalDebt' => $fundamental?->total_debt,
                'debtToEquity' => $fundamental?->debt_to_equity,
                'currentRatio' => $fundamental?->current_ratio,
                'quickRatio' => $fundamental?->quick_ratio,
                'operatingCashflow' => $fundamental?->operating_cash_flow,
                'freeCashflow' => $fundamental?->free_cash_flow,
                'sharesOutstanding' => $fundamental?->shares_outstanding,
                'floatShares' => $fundamental?->float_shares,
            ], fn (mixed $value): bool => $value !== null),
        );
        $instrumentMeta = $this->decodeJson($instrument->meta);
        $predictionExplanation = $this->decodeJson($prediction?->explanation);
        $predictionMetadata = $this->decodeJson($prediction?->metadata);
        $sectorRankings = $this->sectorRankings($instrument, $fundamentalData);

        ['candles' => $chartCandles, 'source' => $chartSource] = $historicalChartAllowed
            ? $this->chartSeries($instrument, $yahooFinance, $chartFocusAt)
            : ['candles' => collect(), 'source' => 'license_restricted'];
        $chartPatterns = $this->recentChartPatterns($chartCandles);
        $chartPatternStats = $historicalChartAllowed
            ? $this->chartPatternStatistics((int) $instrument->id)
            : [];
        $chartStartAt = $chartCandles->isNotEmpty()
            ? CarbonImmutable::createFromTimestampMs((int) $chartCandles->first()['x'])->startOfDay()
            : null;
        $chartEndAt = $chartCandles->isNotEmpty()
            ? CarbonImmutable::createFromTimestampMs((int) $chartCandles->last()['x'])->endOfDay()
            : null;
        $historicalAiHistory = $chartStartAt && $chartEndAt
            ? DB::table('predictions as prediction')
                ->where('prediction.instrument_id', $instrument->id)
                ->whereBetween('prediction.prediction_time', [$chartStartAt, $chartEndAt])
                ->whereNotNull('prediction.prediction_score')
                ->orderByDesc('prediction.prediction_time')
                ->orderByDesc('prediction.id')
                ->select(['prediction.prediction_time', 'prediction.prediction_score', 'prediction.signal'])
                ->selectRaw("{$signalSql} AS personalized_signal")
                ->get()
                ->unique(fn (object $row): string => CarbonImmutable::parse($row->prediction_time)->format('Y-m-d'))
                ->reverse()
                ->values()
                ->map(fn (object $row): array => [
                    'x' => CarbonImmutable::parse($row->prediction_time)->getTimestampMs(),
                    'y' => \App\Support\AiScore::toTen($row->prediction_score),
                    'signal' => strtoupper((string) ($row->personalized_signal ?: $row->signal ?: 'HOLD')),
                ])
                ->filter(fn (array $point): bool => is_numeric($point['y']))
                ->values()
            : collect();
        $historicalAiScores = $historicalAiHistory->map(fn (array $point): array => [
            'x' => $point['x'],
            'y' => $point['y'],
        ]);
        $historicalSignalTransitions = $historicalAiHistory
            ->values()
            ->filter(fn (array $point, int $index): bool =>
                $index > 0 && $point['signal'] !== $historicalAiHistory->values()->get($index - 1)['signal'])
            ->map(function (array $point, int $index) use ($historicalAiHistory): array {
                $previous = $historicalAiHistory->values()->get($index - 1);

                return [
                    'x' => $point['x'],
                    'from' => $previous['signal'],
                    'to' => $point['signal'],
                    'score' => $point['y'],
                ];
            })
            ->values();
        $latestSignalTransition = $historicalSignalTransitions->last();
        $watchlistEntry = $this->watchlistEntry($instrument->id);
        $userWatchlists = DB::table('watchlists')
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        $instrumentWatchlistIds = $userWatchlists->isEmpty()
            ? collect()
            : DB::table('watchlist_items')
                ->where('instrument_id', $instrument->id)
                ->whereIn('watchlist_id', $userWatchlists->pluck('id'))
                ->pluck('watchlist_id')
                ->map(fn ($id) => (int) $id);
        $paperPortfolios = DB::table('portfolios')->leftJoin('portfolio_cash_accounts as cash', 'cash.portfolio_id', '=', 'portfolios.id')
            ->where('portfolios.user_id', auth()->id())->where('portfolios.active', true)->where('portfolios.type', 'paper')
            ->orderByDesc('portfolios.is_default')->get(['portfolios.id','portfolios.name','portfolios.currency','portfolios.meta',DB::raw('COALESCE(cash.balance - cash.reserved_balance, 0) AS available_capital')]);

        $predictionData = $prediction ? [
            'prediction_time' => $prediction->prediction_time,
            'prediction_score' => $prediction->prediction_score,
            'confidence' => $prediction->confidence,
            'risk_score' => $prediction->risk_score,
            'signal_strength' => $prediction->signal_strength,
            'trend_strength' => $prediction->trend_strength,
            'quality_gate_passed' => $prediction->quality_gate_passed,
            'quality_gate_score' => $prediction->quality_gate_score,
        ] : [];
        $ensembleQuality = $this->decodeJson($prediction?->ensemble_quality);
        $ensembleData = collect([
            __('Ensemble-Score') => $ensembleQuality['score'] ?? null,
            __('Qualitätsstufe') => match (strtolower((string) ($ensembleQuality['label'] ?? ''))) {
                'excellent' => __('Exzellent'),
                'strong' => __('Stark'),
                'solid' => __('Solide'),
                'weak' => __('Schwach'),
                default => $ensembleQuality['label'] ?? null,
            },
            __('Ensemble stabil') => $prediction?->ensemble_dispersion_stable,
            __('Relative Streuung') => is_numeric($prediction?->ensemble_relative_dispersion)
                ? (float) $prediction->ensemble_relative_dispersion
                : null,
            __('Modellübereinstimmung') => $ensembleQuality['agreement']
                ?? $ensembleQuality['agreement_score']
                ?? $ensembleQuality['direction_agreement']
                ?? $ensembleQuality['consensus']
                ?? null,
            __('Ensemble-Modelle') => $ensembleQuality['model_count']
                ?? $ensembleQuality['models_count']
                ?? $ensembleQuality['n_models']
                ?? $ensembleQuality['participating_models']
                ?? null,
            __('Ø Modellqualität') => $ensembleQuality['average_model_quality'] ?? null,
            __('Schwächste Modellqualität') => $ensembleQuality['weakest_model_quality'] ?? null,
            __('Ø Stabilität') => $ensembleQuality['average_stability'] ?? null,
            __('Ø Profit-Faktor') => $ensembleQuality['average_profit_factor'] ?? null,
            __('Statistische Zuverlässigkeit') => $ensembleQuality['statistical_reliability'] ?? null,
            __('Ensemble-Veto') => $prediction?->ensemble_dispersion_veto_used,
        ])->all();
        $indicatorCards = $this->indicatorCards($instrument);
        $chartDataUrl = route('stocks.chart-data', $requestedPredictionId > 0
            ? ['symbol' => $instrument->symbol, 'prediction' => $requestedPredictionId]
            : ['symbol' => $instrument->symbol]);
        $stockHeatmapQuery = DB::table('backtest_trades as backtest_trade')
            ->join('backtest_runs as backtest_run', 'backtest_run.id', '=', 'backtest_trade.backtest_run_id')
            ->where('backtest_trade.instrument_id', $instrument->id)
            ->where('backtest_trade.backtest_run_id', function ($query) use ($instrument): void {
                $query->from('backtest_runs as latest_backtest_run')
                    ->join('backtest_trades as latest_backtest_trade', 'latest_backtest_trade.backtest_run_id', '=', 'latest_backtest_run.id')
                    ->where('latest_backtest_trade.instrument_id', $instrument->id)
                    ->whereIn('status', ['completed', 'completed_with_errors'])
                    ->orderByDesc('latest_backtest_run.id')
                    ->limit(1)
                    ->select('latest_backtest_run.id');
            });
        $stockHeatmapSummary = (clone $stockHeatmapQuery)
            ->selectRaw('COUNT(*) AS trades')
            ->selectRaw('AVG(CASE WHEN backtest_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN backtest_trade.net_return > 0 THEN backtest_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN backtest_trade.net_return < 0 THEN backtest_trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(backtest_trade.max_drawdown) * 100 AS drawdown')
            ->first();
        $stockHeatmap = $stockHeatmapQuery
            ->selectRaw('LEAST(9, GREATEST(0, FLOOR(backtest_trade.ki_score)))::integer AS score_bucket')
            ->selectRaw('LEAST(9, GREATEST(0, FLOOR(backtest_trade.confidence / 10)))::integer AS confidence_bucket')
            ->selectRaw('COUNT(*) AS samples')
            ->selectRaw('AVG(CASE WHEN backtest_trade.net_return > 0 THEN 1.0 ELSE 0.0 END) * 100 AS hit_rate')
            ->selectRaw('SUM(CASE WHEN backtest_trade.net_return > 0 THEN backtest_trade.net_return ELSE 0 END) / NULLIF(ABS(SUM(CASE WHEN backtest_trade.net_return < 0 THEN backtest_trade.net_return ELSE 0 END)), 0) AS profit_factor')
            ->selectRaw('MAX(backtest_trade.max_drawdown) * 100 AS drawdown')
            ->groupByRaw('LEAST(9, GREATEST(0, FLOOR(backtest_trade.ki_score)))::integer, LEAST(9, GREATEST(0, FLOOR(backtest_trade.confidence / 10)))::integer')
            ->get()
            ->keyBy(fn ($row) => $row->score_bucket.'-'.$row->confidence_bucket);
        return view('stocks.show', compact(
            'instrument',
            'prediction',
            'modelQuality',
            'detailWalkForwardStats',
            'modelQualityGateReasons',
            'modelChallenger',
            'aiAssessment',
            'aiAssessmentOpportunities',
            'aiAssessmentRisks',
            'aiAssessmentFactors',
            'topStockAnalysis',
            'topStockAnalysisDetails',
            'topStockFactorRatings',
            'predictionData',
            'ensembleData',
            'predictionExplanation',
            'predictionMetadata',
            'fundamental',
            'fundamentalData',
            'sectorRankings',
            'instrumentMeta',
            'chartCandles',
            'chartSource',
            'chartPatterns',
            'chartPatternStats',
            'historicalAiScores',
            'historicalSignalTransitions',
            'latestSignalTransition',
            'chartFocusAt',
            'chartDataUrl',
            'requestedPredictionId',
            'signalChangedAt',
            'returnTo',
            'returnLabel',
            'watchlistEntry',
            'userWatchlists',
            'instrumentWatchlistIds',
            'paperPortfolios',
            'indicatorCards',
            'stockHeatmap',
            'stockHeatmapSummary',
            'canViewRealtime',
            'canUseChartIndicators',
            'canViewChartPatterns',
            'canUseChartZoom',
            'marketSession',
            'historicalChartAllowed',
            'historicalChartRestrictionReason',
            'horizonTargets',
            'horizonStability',
        ));
    }

    public function liveQuote(Request $request, string $symbol, PlanAccessService $planAccess, TwelveDataService $marketData): JsonResponse
    {
        abort_unless($request->user() && $planAccess->allowsTariff($request->user(), PlanLevel::Pro), 403);
        $instrument = $this->instrument($symbol);
        $marketSession = $this->marketSession($instrument);
        if (! $marketSession['open']) {
            return response()->json([
                'symbol' => (string) $instrument->symbol,
                'market_open' => false,
                'realtime' => false,
                'timezone' => $marketSession['timezone'],
                'message' => __('Die Börse ist aktuell geschlossen.'),
            ]);
        }
        $usesGermanListing = filled($instrument->german_listing_symbol)
            && strtoupper((string) $instrument->german_listing_currency) === 'EUR';
        $providerSymbol = (string) ($usesGermanListing
            ? $instrument->german_listing_symbol
            : ($instrument->provider_symbol ?: $instrument->symbol));
        $streamQuote = Cache::get('twelve_data_stream_quote_'.sha1(strtoupper((string) $instrument->symbol)));

        try {
            $quote = is_numeric($streamQuote['price'] ?? null)
                ? $streamQuote
                : ($usesGermanListing
                    ? $marketData->listingQuote($providerSymbol, $instrument->german_listing_exchange ?: null)
                    : $marketData->liveQuote($providerSymbol));
        } catch (Throwable) {
            $quote = null;
        }

        if (! is_numeric($quote['price'] ?? null)) {
            return response()->json([
                'message' => __('Aktuell ist kein Livekurs verfügbar.'),
            ], 503);
        }

        return response()->json([
            'symbol' => (string) $instrument->symbol,
            'price' => (float) $quote['price'],
            'currency' => $usesGermanListing ? 'EUR' : (string) (($quote['currency'] ?? null) ?: $instrument->currency ?: ''),
            'change_percent' => is_numeric($quote['change_percent'] ?? null)
                ? (float) $quote['change_percent']
                : null,
            'timestamp' => is_numeric($quote['timestamp'] ?? null)
                ? (int) $quote['timestamp']
                : now()->timestamp,
            'provider' => 'TwelveData',
            'market_open' => true,
            'realtime' => true,
            'timezone' => $marketSession['timezone'],
        ]);
    }

    public function chartData(
        Request $request,
        string $symbol,
        TwelveDataService $yahooFinance,
        MarketDataEntitlementService $marketDataEntitlements,
    ): JsonResponse
    {
        $instrument = $this->instrument($symbol);
        if (! $marketDataEntitlements->historicalChartsAllowed($instrument)) {
            return response()->json([
                'symbol' => $instrument->symbol,
                'candles' => [],
                'source' => 'license_restricted',
                'chart_patterns' => [],
                'historical_chart_allowed' => false,
                'message' => $marketDataEntitlements->historicalChartRestrictionReason($instrument),
                'updated_at' => now()->toIso8601String(),
            ]);
        }
        $requestedPredictionId = $request->integer('prediction');
        $chartFocusAt = null;

        if ($requestedPredictionId > 0) {
            $predictionTime = DB::table('predictions')
                ->where('id', $requestedPredictionId)
                ->where('instrument_id', $instrument->id)
                ->value('prediction_time');
            abort_unless($predictionTime, 404);
            $chartFocusAt = CarbonImmutable::parse($predictionTime);
        }

        $series = $this->chartSeries($instrument, $yahooFinance, $chartFocusAt);

        return response()->json([
            'symbol' => $instrument->symbol,
            'candles' => $series['candles']->values(),
            'source' => $series['source'],
            'chart_patterns' => $this->recentChartPatterns($series['candles']),
            'watchlist_entry' => $this->watchlistEntry($instrument->id),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /** Detect the latest occurrence of every supported formation in the loaded chart range. */
    private function recentChartPatterns($candles): array
    {
        $candles = collect($candles)->values();
        $patterns = [];
        $start = 1;

        $add = static function (array &$patterns, string $key, string $label, string $direction, int $fromIndex, int $toIndex, $candles): void {
            $from = $candles->get($fromIndex);
            $to = $candles->get($toIndex);
            if (! is_array($from) || ! is_array($to)) return;
            $range = collect(array_merge((array) ($from['y'] ?? []), (array) ($to['y'] ?? [])))
                ->filter(fn ($value): bool => is_numeric($value));
            if ($range->isEmpty()) return;

            $patterns[$key] = [
                'name' => $label,
                'direction' => $direction,
                'from' => (int) $from['x'],
                'to' => (int) $to['x'],
                'low' => (float) $range->min(),
                'high' => (float) $range->max(),
            ];
        };

        for ($index = $start; $index < $candles->count(); $index++) {
            $bar = $candles->get($index);
            $previous = $candles->get($index - 1);
            if (! is_array($bar) || ! is_array($previous) || count($bar['y'] ?? []) < 4 || count($previous['y'] ?? []) < 4) continue;
            [$open, $high, $low, $close] = array_map('floatval', $bar['y']);
            [$previousOpen, $previousHigh, $previousLow, $previousClose] = array_map('floatval', $previous['y']);

            if ($close > $open && $previousClose < $previousOpen && $open <= $previousClose && $close >= $previousOpen) {
                $add($patterns, 'bullish-engulfing', __('Bullish Engulfing'), 'bullish', $index - 1, $index, $candles);
            } elseif ($close < $open && $previousClose > $previousOpen && $open >= $previousClose && $close <= $previousOpen) {
                $add($patterns, 'bearish-engulfing', __('Bearish Engulfing'), 'bearish', $index - 1, $index, $candles);
            }

            $body = abs($close - $open);
            $range = $high - $low;
            if ($range > 0) {
                $lowerWick = min($open, $close) - $low;
                $upperWick = $high - max($open, $close);
                if ($lowerWick >= 2 * max($body, $range * .05) && $upperWick <= $body) {
                    $add($patterns, 'bullish-pin-bar', __('Bullish Pin Bar'), 'bullish', $index, $index, $candles);
                }
                if ($upperWick >= 2 * max($body, $range * .05) && $lowerWick <= $body) {
                    $add($patterns, 'bearish-pin-bar', __('Bearish Pin Bar'), 'bearish', $index, $index, $candles);
                }
            }

            if ($index >= 20) {
                $prior = $candles->slice($index - 20, 20);
                $priorHigh = $prior->max(fn (array $c): float => (float) ($c['y'][1] ?? 0));
                $priorLow = $prior->min(fn (array $c): float => (float) ($c['y'][2] ?? INF));
                if ($close > $priorHigh) $add($patterns, 'upside-breakout', __('Ausbruch nach oben'), 'bullish', $index, $index, $candles);
                if ($close < $priorLow) $add($patterns, 'downside-breakout', __('Ausbruch nach unten'), 'bearish', $index, $index, $candles);
            }
        }

        return array_values($patterns);
    }

    private function chartPatternStatistics(int $instrumentId): array
    {
        $cutoff = now()->subYears(3)->startOfDay();
        $bars = DB::table('price_bars')
            ->where('instrument_id', $instrumentId)
            ->where('interval', '1d')
            ->where('bar_time', '>=', $cutoff->copy()->subDays(60))
            ->orderBy('bar_time')
            ->get(['bar_time', 'open', 'high', 'low', 'close'])
            ->values();
        $definitions = [
            'bullish-engulfing' => [__('Bullish Engulfing'), 'bullish'],
            'bearish-engulfing' => [__('Bearish Engulfing'), 'bearish'],
            'bullish-pin-bar' => [__('Bullish Pin Bar'), 'bullish'],
            'bearish-pin-bar' => [__('Bearish Pin Bar'), 'bearish'],
            'upside-breakout' => [__('Ausbruch nach oben'), 'bullish'],
            'downside-breakout' => [__('Ausbruch nach unten'), 'bearish'],
        ];
        $occurrences = collect($definitions)->mapWithKeys(fn ($definition, $key): array => [$key => []])->all();

        for ($index = 1; $index < $bars->count(); $index++) {
            $bar = $bars[$index];
            $previous = $bars[$index - 1];
            $open = (float) $bar->open; $high = (float) $bar->high; $low = (float) $bar->low; $close = (float) $bar->close;
            $previousOpen = (float) $previous->open; $previousClose = (float) $previous->close;
            $found = [];
            if ($close > $open && $previousClose < $previousOpen && $open <= $previousClose && $close >= $previousOpen) $found[] = 'bullish-engulfing';
            if ($close < $open && $previousClose > $previousOpen && $open >= $previousClose && $close <= $previousOpen) $found[] = 'bearish-engulfing';
            $body = abs($close - $open); $range = $high - $low;
            if ($range > 0) {
                $lowerWick = min($open, $close) - $low; $upperWick = $high - max($open, $close);
                if ($lowerWick >= 2 * max($body, $range * .05) && $upperWick <= $body) $found[] = 'bullish-pin-bar';
                if ($upperWick >= 2 * max($body, $range * .05) && $lowerWick <= $body) $found[] = 'bearish-pin-bar';
            }
            if ($index >= 20) {
                $prior = $bars->slice($index - 20, 20);
                if ($close > (float) $prior->max('high')) $found[] = 'upside-breakout';
                if ($close < (float) $prior->min('low')) $found[] = 'downside-breakout';
            }
            if (CarbonImmutable::parse($bar->bar_time)->lt($cutoff)) continue;
            foreach (array_unique($found) as $key) {
                $future = $bars->get($index + 20);
                $return = $future && $close !== 0.0 ? (((float) $future->close / $close) - 1) * 100 : null;
                $example = $bars->slice(max(0, $index - 3), 7)->map(fn (object $exampleBar): array => [
                    'open' => (float) $exampleBar->open,
                    'high' => (float) $exampleBar->high,
                    'low' => (float) $exampleBar->low,
                    'close' => (float) $exampleBar->close,
                ])->values()->all();
                $occurrences[$key][] = ['at' => $bar->bar_time, 'return' => $return, 'example' => $example];
            }
        }

        return collect($definitions)->map(function (array $definition, string $key) use ($occurrences): array {
            [$name, $direction] = $definition;
            $items = collect($occurrences[$key]);
            $validated = $items->whereNotNull('return');
            $directionalReturns = $validated->pluck('return')->map(fn ($return): float => (float) $return * ($direction === 'bearish' ? -1 : 1));
            $latest = $items->last();
            return [
                'key' => $key,
                'name' => $name,
                'direction' => $direction,
                'latest_at' => $latest['at'] ?? null,
                'example' => $latest['example'] ?? [],
                'samples' => $validated->count(),
                'hit_rate' => $directionalReturns->isNotEmpty() ? $directionalReturns->filter(fn (float $return): bool => $return > 0)->count() / $directionalReturns->count() * 100 : null,
                'average_performance' => $directionalReturns->isNotEmpty() ? $directionalReturns->avg() : null,
            ];
        })->values()->all();
    }

    private function indicatorCards(object $instrument)
    {
        $indicators = DB::table('technical_indicators')
            ->where('instrument_id', $instrument->id)
            ->where('interval', '1d')
            ->where('bar_time', '>=', now()->subYears(3)->startOfDay())
            ->orderByDesc('bar_time')
            ->get([
                'bar_time', 'sma_20', 'sma_50', 'sma_200', 'ema_20', 'ema_50',
                'bollinger_upper', 'bollinger_middle', 'bollinger_lower', 'bollinger_width',
                'rsi_14', 'macd', 'macd_signal', 'macd_histogram', 'adx_14', 'atr_14',
                'stochastic_k', 'stochastic_d', 'volatility_20', 'momentum_10',
            ])
            ->reverse()
            ->values();
        $features = $indicators->isEmpty()
            ? collect()
            : DB::table('feature_store')
                ->where('instrument_id', $instrument->id)
                ->where('interval', '1d')
                ->whereIn('bar_time', $indicators->pluck('bar_time'))
                ->get(['bar_time', 'close', 'target_return_20d'])
                ->keyBy(fn (object $row): string => CarbonImmutable::parse($row->bar_time)->toIso8601String());
        $chartRows = $indicators->map(function (object $indicator) use ($features): array {
            $time = CarbonImmutable::parse($indicator->bar_time);
            $feature = $features->get($time->toIso8601String());

            return [
                'x' => $time->getTimestampMs(),
                'c' => $this->number($feature?->close),
                'rsi' => $this->number($indicator?->rsi_14),
                'adx' => $this->number($indicator?->adx_14),
                'stochK' => $this->number($indicator?->stochastic_k),
                'volatility' => $this->number($indicator?->volatility_20),
                'atr' => $this->number($indicator?->atr_14),
                'bbWidth' => $this->number($indicator?->bollinger_width),
                'macdHistogram' => $this->number($indicator?->macd_histogram),
                'momentum10' => $this->number($indicator?->momentum_10),
                'targetReturn20d' => $this->number($feature?->target_return_20d),
            ];
        });
        $definitions = [
            ['label' => 'RSI 14', 'field' => 'rsi', 'unit' => ''],
            ['label' => 'ADX 14', 'field' => 'adx', 'unit' => ''],
            ['label' => 'Stochastik %K', 'field' => 'stochK', 'unit' => ''],
            ['label' => __('Volatilität 20T'), 'field' => 'volatility', 'unit' => '%'],
            ['label' => 'ATR 14', 'field' => 'atr', 'unit' => $instrument->currency ?: ''],
            ['label' => __('Bollinger-Bandbreite'), 'field' => 'bbWidth', 'unit' => '%'],
            ['label' => 'MACD Histogramm', 'field' => 'macdHistogram', 'unit' => ''],
            ['label' => __('Momentum 10T'), 'field' => 'momentum10Pct', 'unit' => '%'],
        ];
        $valueFor = function (?array $row, string $field): ?float {
            if (! $row) return null;
            $close = (float) ($row['c'] ?? 0);

            return match ($field) {
                'momentum10Pct' => abs($close - (float) ($row['momentum10'] ?? 0)) > 0.000001 && $row['momentum10'] !== null
                    ? $row['momentum10'] / ($close - $row['momentum10'])
                    : null,
                default => isset($row[$field]) && is_numeric($row[$field]) ? (float) $row[$field] : null,
            };
        };
        $currentRow = $chartRows->last();
        $fiveDayRow = $chartRows->count() >= 6 ? $chartRows->get($chartRows->count() - 6) : null;

        return collect($definitions)->map(function (array $definition) use ($chartRows, $currentRow, $fiveDayRow, $valueFor): array {
            $scale = $definition['unit'] === '%' ? 100.0 : 1.0;
            $currentRawValue = $valueFor($currentRow, $definition['field']);
            $fiveDayRawValue = $valueFor($fiveDayRow, $definition['field']);
            $fiveDayChange = $currentRawValue !== null && $fiveDayRawValue !== null
                ? ($currentRawValue - $fiveDayRawValue) * $scale
                : null;
            $points = $chartRows->map(function (array $row) use ($definition, $scale, $valueFor): ?array {
                $rawValue = $valueFor($row, $definition['field']);
                if ($rawValue === null || ! is_numeric($row['targetReturn20d'])) return null;
                $return = (float) $row['targetReturn20d'];

                return [
                    'x' => $rawValue * $scale,
                    'y' => $return * 100,
                    'up' => $return > 0,
                    'date' => CarbonImmutable::createFromTimestampMs($row['x'])->format('d.m.Y'),
                ];
            })->filter()->values();
            $nearby = $currentRawValue === null
                ? collect()
                : $points->sortBy(fn (array $point): float => abs($point['x'] - ($currentRawValue * $scale)))
                    ->take(min(40, $points->count()));
            $riseProbability = $nearby->isEmpty() ? null : ($nearby->where('up', true)->count() / $nearby->count()) * 100;

            return [
                ...$definition,
                'currentValue' => $currentRawValue === null ? null : $currentRawValue * $scale,
                'fiveDayChange' => $fiveDayChange,
                'fiveDayDirection' => $fiveDayChange === null ? null : (abs($fiveDayChange) < 0.000001 ? 'flat' : ($fiveDayChange > 0 ? 'up' : 'down')),
                'currentProbability' => $riseProbability,
                'currentFallProbability' => $riseProbability === null ? null : 100 - $riseProbability,
                'comparisonSamples' => $nearby->count(),
                'points' => $points,
            ];
        })->values();
    }

    private function instrument(string $symbol): object
    {
        $instrument = DB::table('instruments')
            ->where('type', 'stock')
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(symbol) = ?', [strtoupper($symbol)])
            ->first();

        abort_unless($instrument, 404);

        return $instrument;
    }

    private function marketSession(object $instrument): array
    {
        $exchange = $instrument->exchange_id
            ? DB::table('exchanges')->where('id', $instrument->exchange_id)->first(['country', 'timezone'])
            : null;
        $timezone = (string) ($exchange?->timezone ?: 'Europe/Berlin');
        try {
            $local = CarbonImmutable::now($timezone);
        } catch (Throwable) {
            $timezone = 'Europe/Berlin';
            $local = CarbonImmutable::now($timezone);
        }

        $country = strtoupper((string) ($exchange?->country ?: $instrument->country ?: 'DE'));
        $sessions = match ($country) {
            'US', 'CA' => [[9 * 60 + 30, 16 * 60]],
            'GB' => [[8 * 60, 16 * 60 + 30]],
            'CN' => [[9 * 60 + 30, 11 * 60 + 30], [13 * 60, 15 * 60]],
            'HK' => [[9 * 60 + 30, 12 * 60], [13 * 60, 16 * 60]],
            'JP' => [[9 * 60, 11 * 60 + 30], [12 * 60 + 30, 15 * 60 + 30]],
            default => [[9 * 60, 17 * 60 + 30]],
        };
        $minute = ($local->hour * 60) + $local->minute;
        $open = $local->isWeekday() && collect($sessions)->contains(
            fn (array $session): bool => $minute >= $session[0] && $minute < $session[1]
        );

        return ['open' => $open, 'timezone' => $timezone, 'local_time' => $local->toIso8601String()];
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function chartSeries(
        object $instrument,
        TwelveDataService $yahooFinance,
        ?CarbonImmutable $focusAt = null,
    ): array
    {
        $bars = $this->dailyBars((int) $instrument->id, $focusAt);

        if ($bars->count() < ($focusAt ? 50 : 252)) {
            try {
                $downloaded = $yahooFinance->dailyCandles(
                    $instrument->provider_symbol ?: $instrument->symbol,
                    $focusAt ? 140 : 300,
                );

                if ($downloaded) {
                    $now = now();
                    $rows = collect($downloaded)->map(fn (array $bar) => [
                        'instrument_id' => (int) $instrument->id,
                        'interval' => '1d',
                        'bar_time' => CarbonImmutable::createFromTimestampUTC($bar['timestamp']),
                        'open' => $bar['open'],
                        'high' => $bar['high'],
                        'low' => $bar['low'],
                        'close' => $bar['close'],
                        'adjusted_close' => $bar['adjusted_close'],
                        'volume' => $bar['volume'],
                        'source' => 'twelve_data',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('price_bars')->upsert(
                        $rows,
                        ['instrument_id', 'interval', 'bar_time'],
                        ['open', 'high', 'low', 'close', 'adjusted_close', 'volume', 'source', 'updated_at'],
                    );
                    $bars = $this->dailyBars((int) $instrument->id, $focusAt);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'candles' => $bars->map(fn ($bar) => [
                'x' => CarbonImmutable::parse($bar->bar_time)->getTimestampMs(),
                'y' => [
                    (float) $bar->open,
                    (float) $bar->high,
                    (float) $bar->low,
                    (float) $bar->close,
                ],
                'volume' => is_numeric($bar->volume) ? (float) $bar->volume : null,
            ]),
            'source' => $bars->isEmpty() ? 'unavailable' : ($bars->every(fn ($bar) => $bar->source === 'twelve_data') ? 'twelve_data' : 'price_bars'),
        ];
    }

    private function dailyBars(int $instrumentId, ?CarbonImmutable $focusAt = null)
    {
        $query = DB::table('price_bars')
            ->where('instrument_id', $instrumentId)
            ->where('interval', '1d');

        if ($focusAt) {
            return $query
                ->whereBetween('bar_time', [
                    $focusAt->subYear()->subDays(35)->startOfDay(),
                    $focusAt->addDays(50)->endOfDay(),
                ])
                ->orderByDesc('bar_time')
                ->get()
                ->unique(fn ($bar) => CarbonImmutable::parse($bar->bar_time)->format('Y-m-d'))
                ->sortBy('bar_time')
                ->values();
        }

        return $query
            ->orderByDesc('bar_time')
            // A provider can persist the same trading day with a different
            // time component. Fetch enough rows, then keep exactly one OHLC
            // record per calendar/trading day.
            ->limit(420)
            ->get()
            ->unique(fn ($bar) => CarbonImmutable::parse($bar->bar_time)->format('Y-m-d'))
            ->take(260)
            ->reverse()
            ->values();
    }

    private function watchlistEntry(int $instrumentId): ?array
    {
        $entry = DB::table('watchlist_items as item')
            ->join('watchlists as watchlist', 'watchlist.id', '=', 'item.watchlist_id')
            ->where('watchlist.user_id', auth()->id())
            ->where('watchlist.active', true)
            ->where('item.instrument_id', $instrumentId)
            ->whereNotNull('item.entry_price')
            ->orderByDesc('watchlist.is_default')
            ->orderByDesc('item.added_at')
            ->select([
                'watchlist.name',
                'item.entry_price',
                'item.entry_price_at',
                'item.entry_currency',
            ])
            ->first();

        if (! $entry || ! is_numeric($entry->entry_price)) {
            return null;
        }

        return [
            'name' => $entry->name,
            'price' => (float) $entry->entry_price,
            'recorded_at' => $entry->entry_price_at,
            'currency' => $entry->entry_currency,
        ];
    }

    private function sectorRankings(object $instrument, array $fundamentalData): array
    {
        if (! $instrument->sector) {
            return [];
        }

        $latestFundamentalIds = DB::table('instrument_fundamentals')
            ->selectRaw('instrument_id, MAX(id) AS fundamental_id')
            ->groupBy('instrument_id');

        $sectorFundamentals = DB::table('instruments as peer')
            ->joinSub($latestFundamentalIds, 'latest_fundamental', fn ($join) =>
                $join->on('latest_fundamental.instrument_id', '=', 'peer.id'))
            ->join('instrument_fundamentals as fundamental', 'fundamental.id', '=', 'latest_fundamental.fundamental_id')
            ->where('peer.type', 'stock')
            ->whereNull('peer.deleted_at')
            ->where('peer.sector', $instrument->sector)
            ->pluck('fundamental.data')
            ->map(fn ($data) => $this->decodeJson($data));

        $definitions = [
            'pe' => ['key' => 'trailingPE', 'direction' => 'asc', 'positive_only' => true],
            'dividend' => ['key' => 'dividendYield', 'direction' => 'desc', 'positive_only' => false],
        ];

        return collect($definitions)
            ->mapWithKeys(function (array $definition, string $name) use ($sectorFundamentals, $fundamentalData): array {
                $current = $fundamentalData[$definition['key']] ?? null;
                if (! is_numeric($current) || ($definition['positive_only'] && (float) $current <= 0)) {
                    return [$name => null];
                }

                $current = (float) $current;
                $values = $sectorFundamentals
                    ->pluck($definition['key'])
                    ->filter(fn ($value) => is_numeric($value)
                        && (! $definition['positive_only'] || (float) $value > 0))
                    ->map(fn ($value) => (float) $value)
                    ->values();

                if ($values->isEmpty()) {
                    return [$name => null];
                }

                $better = $values->filter(fn (float $value) =>
                    $definition['direction'] === 'asc' ? $value < $current : $value > $current
                )->count();

                return [$name => [
                    'rank' => $better + 1,
                    'total' => $values->count(),
                ]];
            })
            ->all();
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        return json_decode($value, true) ?: [];
    }

}

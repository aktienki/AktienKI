<?php

namespace App\Http\Controllers;

use App\Services\PersonalizedSignalService;
use App\Support\QualityGrade;
use App\Support\RiskScore;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PerformanceTransparencyController extends Controller
{
    public function __invoke(Request $request, PersonalizedSignalService $signals): View
    {
        $latestPredictions = DB::table('predictions')
            ->selectRaw('instrument_id, MAX(id) AS prediction_id')
            ->groupBy('instrument_id');
        // Supplemental experiments (for example ARIMA comparisons) live in
        // the same audit table but are not production release decisions. For
        // active instruments show the latest passing production calibration;
        // for inactive instruments show the latest production calibration.
        $latestThresholds = DB::table('stock_individual_thresholds as candidate')
            ->join('instruments as release_instrument', 'release_instrument.id', '=', 'candidate.instrument_id')
            ->where('candidate.horizon_days', 20)
            ->where('candidate.algorithm_version', 'like', 'historical-action-%')
            ->where(function ($query): void {
                $query->where('release_instrument.is_active', false)
                    ->orWhere('candidate.validation_passed', true);
            })
            ->selectRaw('candidate.instrument_id, MAX(candidate.id) AS threshold_id')
            ->groupBy('candidate.instrument_id');
        $personalizedSignalSql = $signals->sql('prediction', $request->user());

        $rows = DB::table('instruments as instrument')
            ->joinSub($latestThresholds, 'latest_threshold', fn ($join) => $join
                ->on('latest_threshold.instrument_id', '=', 'instrument.id'))
            ->join('stock_individual_thresholds as threshold', 'threshold.id', '=', 'latest_threshold.threshold_id')
            ->leftJoinSub($latestPredictions, 'latest_prediction', fn ($join) => $join
                ->on('latest_prediction.instrument_id', '=', 'instrument.id'))
            ->leftJoin('predictions as prediction', 'prediction.id', '=', 'latest_prediction.prediction_id')
            ->where('instrument.type', 'stock')
            ->whereNull('instrument.deleted_at')
            ->select([
                'instrument.id as instrument_id', 'instrument.symbol', 'instrument.name', 'instrument.country',
                'threshold.status', 'threshold.validation_passed', 'threshold.minimum_ai_score',
                'threshold.event_count', 'threshold.validation_event_count', 'threshold.score_result',
                'threshold.calculated_at', 'prediction.id as prediction_id', 'prediction.risk_score',
                'prediction.drawdown_risk_factor',
            ])
            ->selectRaw("COALESCE(({$personalizedSignalSql}), prediction.signal, 'HOLD') AS personalized_signal")
            ->orderByDesc('threshold.validation_passed')
            ->orderBy('instrument.name')
            ->get();

        $rows->transform(function (object $row): object {
            $result = is_array($row->score_result)
                ? $row->score_result
                : (json_decode((string) $row->score_result, true) ?: []);
            $before = (array) data_get($result, 'validation', []);
            $after = (array) data_get($result, 'post_filter_evaluation.selected.oos', $before);
            $quality = (array) data_get($result, 'signal_quality', []);
            $riskPercent = RiskScore::toPercent($row->risk_score, $row->drawdown_risk_factor);

            $row->score_grade = $quality['grade'] ?? QualityGrade::fromPercent(
                is_numeric($quality['quality_percent'] ?? null) ? (float) $quality['quality_percent'] : null
            );
            $row->risk_level = QualityGrade::riskLevel($riskPercent);
            $row->risk_percent = $riskPercent;
            $row->quality_class = data_get($result, 'post_filter_evaluation.selected.quality_class')
                ?? data_get($result, 'raw_pre_filter_quality_class')
                ?? ($row->validation_passed ? 'solid' : 'observation');
            $row->before_metrics = $before;
            $row->after_metrics = $after;
            $row->reason = $this->reason($row, $after);

            return $row;
        });

        $qualitySummary = collect(['quality', 'solid', 'basic', 'observation'])
            ->mapWithKeys(fn (string $class): array => [$class => $rows->where('quality_class', $class)->count()])
            ->all();

        $search = trim((string) $request->query('q', ''));
        $signal = strtoupper(trim((string) $request->query('signal', '')));
        $score = trim((string) $request->query('score', ''));
        $risk = trim((string) $request->query('risk', ''));
        $quality = strtolower(trim((string) $request->query('quality', '')));
        $minimumHitRate = $request->filled('hit_rate') ? (float) $request->query('hit_rate') : null;
        $minimumProfitFactor = $request->filled('profit_factor') ? (float) $request->query('profit_factor') : null;

        $filtered = $rows->filter(function (object $row) use ($search, $signal, $score, $risk, $quality, $minimumHitRate, $minimumProfitFactor): bool {
            if ($search !== '' && ! str_contains(mb_strtolower($row->name.' '.$row->symbol), mb_strtolower($search))) return false;
            if ($signal !== '' && strtoupper((string) $row->personalized_signal) !== $signal) return false;
            if ($score !== '' && (string) $row->score_grade !== $score) return false;
            if ($risk !== '' && (string) $row->risk_level !== $risk) return false;
            if ($quality !== '' && strtolower((string) $row->quality_class) !== $quality) return false;
            if ($minimumHitRate !== null && (float) ($row->after_metrics['hit_rate'] ?? -INF) < $minimumHitRate) return false;
            if ($minimumProfitFactor !== null && (float) ($row->after_metrics['profit_factor'] ?? -INF) < $minimumProfitFactor) return false;
            return true;
        })->values();

        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));
        $rows = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('predictions.performance-transparency', compact('rows', 'qualitySummary'));
    }

    private function reason(object $row, array $metrics): string
    {
        $trades = (int) ($metrics['trades'] ?? $row->validation_event_count ?? 0);
        if ($trades < 10) return __('Vorläufig: weniger als zehn unabhängige Out-of-Sample-Signale.');
        if (! $row->validation_passed) return __('Beobachtung: Die aktuelle Validierung erfüllt noch nicht alle Freigabekriterien.');

        return __('Validiert: individuelle Schwelle und nachgelagerte Filter wurden außerhalb des Trainingszeitraums geprüft.');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TrainingCompletedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class SendTrainingCompletedEmail extends Command
{
    protected $signature = 'training:send-completion {symbol} {--duration= : Duration in seconds} {--source=worker-pool : Training source} {--user= : Recipient user ID} {--email= : Explicit recipient address}';
    protected $description = 'Send a branded completion report for a completed stock training';

    public function handle(): int
    {
        $symbol = strtoupper(trim((string) $this->argument('symbol')));
        $instrument = DB::table('instruments')->whereRaw('UPPER(symbol) = ?', [$symbol])->first();
        if (! $instrument) {
            $this->error("Unknown instrument: {$symbol}");
            return self::FAILURE;
        }

        $recipient = trim((string) ($this->option('email') ?: config('aktienki.training_report_email')));
        $user = $recipient === '' ? User::query()->whereNotNull('email')
            ->when($this->option('user'), fn ($query) => $query->whereKey((int) $this->option('user')))
            ->orderByDesc('last_login_at')->orderByDesc('id')->firstOrFail() : null;

        $runIds = DB::table('walk_forward_backtest_trades')
            ->where('instrument_id', $instrument->id)->distinct()->pluck('run_id');
        $runs = DB::table('walk_forward_backtest_runs as run')
            ->whereIn('run.id', $runIds)
            ->whereIn('run.horizon_days', [5, 10, 15, 20])
            ->where('run.status', 'completed')
            ->select('run.id', 'run.horizon_days', 'run.summary', 'run.finished_at')
            ->orderByDesc('run.id')->get()->unique('horizon_days')->sortBy('horizon_days');

        $horizons = $runs->map(function (object $run): array {
            $summary = is_array($run->summary) ? $run->summary : (json_decode((string) $run->summary, true) ?: []);
            $hitRate = (float) ($summary['hit_rate'] ?? 0);
            $profitFactor = (float) ($summary['profit_factor'] ?? 0);
            $averageReturn = (float) ($summary['average_return'] ?? 0) * 100;
            return [
                'days' => (int) $run->horizon_days,
                'trades' => (int) ($summary['signals'] ?? DB::table('walk_forward_backtest_trades')->where('run_id', $run->id)->count()),
                'hitRate' => number_format($hitRate * 100, 1, ',', '.').' %',
                'profitFactorRaw' => $profitFactor,
                'profitFactor' => number_format($profitFactor, 2, ',', '.'),
                'averageReturnRaw' => $averageReturn,
                'averageReturn' => ($averageReturn >= 0 ? '+' : '').number_format($averageReturn, 2, ',', '.').' %',
            ];
        })->values()->all();

        $threshold = DB::table('stock_individual_thresholds')
            ->where('instrument_id', $instrument->id)->where('horizon_days', 20)
            ->where('algorithm_version', 'like', 'historical-action-%')
            ->when((bool) $instrument->is_active, fn ($query) => $query->where('validation_passed', true))
            ->orderByDesc('updated_at')->first();
        $thresholdResult = $threshold ? (json_decode((string) $threshold->score_result, true) ?: []) : [];
        $filteredMetrics = data_get($thresholdResult, 'post_filter_evaluation.selected.oos')
            ?: data_get($thresholdResult, 'validation')
            ?: [];
        $duration = is_numeric($this->option('duration')) ? (int) $this->option('duration') : null;
        $status = (string) ($threshold?->status ?: 'documented');

        $notification = new TrainingCompletedNotification([
            'symbol' => $symbol,
            'name' => (string) $instrument->name,
            'sourceLabel' => match ((string) $this->option('source')) {
                'macbook-arima' => 'MacBook · ARIMA-Zeitreihe',
                'workstation' => 'Workstation · Basismodelle',
                default => 'Automatisierte Trainingspipeline',
            },
            'finishedAt' => now()->format('d.m.Y · H:i'),
            'duration' => $duration !== null ? sprintf('%d Min. %02d Sek.', intdiv($duration, 60), $duration % 60) : '–',
            'validationPassed' => (bool) ($threshold?->validation_passed ?? false),
            'statusLabel' => (bool) ($threshold?->validation_passed ?? false) ? 'Validiert' : 'Dokumentiert · nicht freigegeben',
            'qualityClass' => ucfirst(str_replace(['_active', '_'], ['', ' '], $status)),
            'minimumAiScore' => is_numeric($threshold?->minimum_ai_score) ? number_format((float) $threshold->minimum_ai_score, 1, ',', '.') : '–',
            'filteredPerformance' => [
                'trades' => (int) ($filteredMetrics['trades'] ?? 0),
                'hitRate' => is_numeric($filteredMetrics['hit_rate'] ?? null) ? number_format((float) $filteredMetrics['hit_rate'], 1, ',', '.').' %' : '–',
                'profitFactor' => is_numeric($filteredMetrics['profit_factor'] ?? null) ? number_format((float) $filteredMetrics['profit_factor'], 2, ',', '.') : '–',
                'averageReturn' => is_numeric($filteredMetrics['average_return_percent'] ?? null)
                    ? (((float) $filteredMetrics['average_return_percent'] >= 0 ? '+' : '').number_format((float) $filteredMetrics['average_return_percent'], 2, ',', '.').' %') : '–',
                'available' => $filteredMetrics !== [],
            ],
            'horizons' => $horizons,
        ]);
        if ($recipient !== '') {
            Notification::route('mail', $recipient)->notifyNow($notification);
        } else {
            $user?->notifyNow($notification);
        }

        $this->info("Training completion email sent for {$symbol}.");
        return self::SUCCESS;
    }
}

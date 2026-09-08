<?php

namespace App\Console\Commands;

use App\Services\PumaExitRecommendationService;
use Illuminate\Console\Command;
use Throwable;

final class ShowPumaExitRecommendation extends Command
{
    protected $signature = 'puma-exit:show {--json : Vollständigen Datensatz als JSON ausgeben}';

    protected $description = 'Liest die aktive PUMA Exit-Empfehlung aus der Serving-Datenbank.';

    public function handle(PumaExitRecommendationService $recommendations): int
    {
        try {
            $result = $recommendations->current();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Field', 'Value'], [
            ['Instrument', "{$result['instrument_id']} / {$result['isin']} / {$result['provider_symbol']}"],
            ['Policy', $result['policy_name']],
            ['Version', $result['policy_version']],
            ['Decision', $result['decision']],
            ['Model decision', $result['model_decision']],
            ['Recommendation status', $result['recommendation_status']],
            ['Recommendation actionable', $result['recommendation_actionable'] ? 'yes' : 'no'],
            ['Market session', $result['market_session_date'] ?? 'no data'],
            ['Valid until', $result['valid_until'] ?? 'no data'],
            ['State only', ($result['state_only'] ?? true) ? 'yes' : 'no'],
            ['Execution timing', $result['execution_timing']],
            ['Automatic execution', $result['automatic_execution'] ? 'yes' : 'no'],
            ['Release gate passed', $result['release_gate_passed'] ? 'yes' : 'no'],
            ['User override', $result['user_override'] ? 'yes' : 'no'],
            ['Comparison baseline', $result['comparison_baseline']],
            ['Maximum holding sessions', $result['maximum_holding_sessions'] ?? 'none'],
        ]);

        return self::SUCCESS;
    }
}

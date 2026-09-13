<?php

namespace App\Console\Commands;

use App\Http\Controllers\QualityGateSetupController;
use App\Http\Controllers\SavedPredictionFilterController;
use App\Models\SavedPredictionFilter;
use App\Models\User;
use Illuminate\Console\Command;

final class CreateQualityGateStrategy extends Command
{
    protected $signature = 'strategy:create-quality-gate
        {user=1 : Benutzer-ID oder E-Mail-Adresse}
        {--name=Quality Gate Strategie : Name der Strategie}';

    protected $description = 'Erstellt oder aktualisiert eine gespeicherte Fünf-Aktien-Strategie mit den Quality-Gate-Standardregeln.';

    public function handle(): int
    {
        $identity = trim((string) $this->argument('user'));
        $user = User::query()
            ->when(ctype_digit($identity), fn ($query) => $query->whereKey((int) $identity), fn ($query) => $query->where('email', $identity))
            ->first();

        if ($user === null) {
            $this->error("Benutzer {$identity} wurde nicht gefunden.");

            return self::FAILURE;
        }

        $rules = QualityGateSetupController::DEFAULTS;
        $filters = array_merge(SavedPredictionFilterController::FILTER_DEFAULTS, [
            'quality_tier' => (string) $rules['minimum_tier'],
            'service_quality_gate' => 'passed',
            'score_min' => (float) $rules['score_min'],
            'confidence_min' => (float) $rules['confidence_min'],
            'risk_max' => (float) $rules['risk_max'],
            'predicted_return_min' => (float) $rules['predicted_return_min'],
            'drawdown_max' => (float) $rules['drawdown_max'],
            'profit_factor_min' => (float) $rules['profit_factor_min'],
            'hit_rate_min' => (float) $rules['hit_rate_min'],
            'minimum_trades' => (int) $rules['minimum_trades'],
            'positive_prediction_required' => (int) $rules['positive_prediction_required'],
            'ensemble_veto_required' => (int) $rules['ensemble_veto_required'],
            'gate_mode' => 'system',
            'entry_strategy' => 'direct_buy',
            'entry_risk_style' => 'balanced',
            'initial_capital' => 10000,
            'max_positions' => 5,
            'position_factor' => 1,
            'trade_cost' => 10,
            'display_icon' => 'shield-check',
            'display_color' => '#10B981',
        ]);

        $strategy = SavedPredictionFilter::query()->updateOrCreate(
            ['user_id' => $user->id, 'name' => trim((string) $this->option('name'))],
            [
                'filters' => $filters,
                'visibility' => 'private',
                'description' => 'Nur Modelle mit bestandenem Service Quality Gate; fünf Depotplätze und die systemweiten Qualitätsgrenzen.',
                'published_at' => null,
            ],
        );

        $this->info("Strategie #{$strategy->id} „{$strategy->name}“ wurde für {$user->email} gespeichert.");

        return self::SUCCESS;
    }
}

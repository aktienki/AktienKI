<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

final class ProjectStatusController extends Controller
{
    public function __invoke(): View
    {
        $betaLimit = 50;
        $databaseAvailable = false;
        $stats = [
            'testers' => 0,
            'stocks' => null,
            'indices' => null,
            'predictions' => null,
            'validated' => null,
            'models' => null,
            'last_prediction_at' => null,
        ];
        $modelAliases = collect();

        try {
            DB::select('SELECT 1');
            DB::connection('serving')->select('SELECT 1');
            $databaseAvailable = true;
            $serving = DB::connection('serving');

            $stats = [
                // Accounts remain intentionally in the primary application DB.
                'testers' => min($betaLimit, DB::table('users')->where('account_status', 'tester')->count()),
                'stocks' => $serving->table('serving_active_models as active_model')
                    ->join('serving_instruments as instrument', 'instrument.id', '=', 'active_model.instrument_id')
                    ->where('instrument.is_active', true)
                    ->count(),
                'indices' => $serving->table('serving_instruments')
                    ->where('instrument_type', 'index')->where('is_active', true)->count(),
                'predictions' => $serving->table('serving_predictions')->count(),
                'validated' => $serving->table('serving_predictions as prediction')
                    ->join('serving_prediction_scopes as scope', function ($join): void {
                        $join->on('scope.instrument_id', '=', 'prediction.instrument_id')
                            ->on('scope.release_id', '=', 'prediction.release_id')
                            ->on('scope.horizon', '=', 'prediction.horizon');
                    })->count(),
                'models' => $serving->table('serving_active_models')->count(),
                'last_prediction_at' => $serving->table('serving_predictions')->max('as_of'),
            ];

            $modelAliases = $serving->table('serving_model_horizon_status')
                ->where('selected_for_prediction', true)
                ->distinct()
                ->orderBy('horizon')
                ->orderBy('variant')
                ->get(['variant', 'horizon'])
                ->map(fn (object $model): object => (object) [
                    'public_alias' => $model->variant === 'pure_tcn' ? 'TCN' : 'Standard',
                    'ai_type' => ((int) $model->horizon).'T',
                ]);
        } catch (Throwable $error) {
            report($error);
            // The public status page remains available during temporary outages.
        }

        $betaProgress = $betaLimit > 0
            ? min(100, ($stats['testers'] / $betaLimit) * 100)
            : 100;

        return view('project-status', compact(
            'betaLimit',
            'betaProgress',
            'databaseAvailable',
            'stats',
            'modelAliases',
        ));
    }
}

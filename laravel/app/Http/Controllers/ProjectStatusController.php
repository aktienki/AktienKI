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
            $databaseAvailable = true;

            $stats = [
                'testers' => min($betaLimit, DB::table('users')->where('account_status', 'tester')->count()),
                'stocks' => DB::table('instruments')->where('type', 'stock')->whereNull('deleted_at')->count(),
                'indices' => DB::table('market_indices')->where('is_active', true)->count(),
                'predictions' => DB::table('predictions')->count(),
                'validated' => DB::table('predictions')->whereNotNull('validated_at')->count(),
                'models' => DB::table('model_definitions')->where('is_active', true)->where('is_public', true)->count(),
                'last_prediction_at' => DB::table('predictions')->max('prediction_time'),
            ];

            $modelAliases = DB::table('model_definitions')
                ->where('is_active', true)
                ->where('is_public', true)
                ->whereNotNull('public_alias')
                ->orderBy('ai_type')
                ->orderBy('public_alias')
                ->get(['public_alias', 'ai_type']);
        } catch (Throwable) {
            // The public status page remains available during temporary database outages.
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

<?php

namespace App\Services;

use App\Models\ModelRun;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PredictionService
{
    public function __construct(
        protected YahooFinanceService $yahoo
    ) {}

    public function topSignals(int $limit = 5): array
    {
        $lastRun = ModelRun::query()
            ->latest('finished_at')
            ->latest('updated_at')
            ->first();

        return Prediction::query()
            ->with('company')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('prediction_models')
                    ->whereColumn(
                        'prediction_models.prediction_id',
                        'predictions.id'
                    );
            })
            ->orderByDesc('prediction_score')
            ->limit($limit)
            ->get()
            ->map(function ($prediction) use ($lastRun) {

                $company = $prediction->company;

                $quote = null;
                $sparkline = [];

                if ($company?->symbol) {
                    $quote = $this->yahoo->quote($company->symbol);
                    $sparkline = $this->yahoo->sparkline($company->symbol);
                }

                $models = DB::table('prediction_models')
                    ->where('prediction_id', $prediction->id)
                    ->orderByDesc('score')
                    ->limit(3)
                    ->get()
                    ->map(function ($model) {

                        $raw = (float) ($model->score ?? 0);

                        $percent = $raw <= 1
                            ? $raw * 100
                            : $raw;

                        return [
                            'name' => $model->model,
                            'score' => round($percent,1),
                            'bar' => min(100,max(0,$percent)),
                            'confidence' => round(((float)$model->confidence)*100,1),
                            'accuracy' => round(((float)$model->accuracy)*100,1),
                            'hitrate' => round(((float)$model->hitrate)*100,1),
                        ];

                    })
                    ->values()
                    ->toArray();

                return [

                    'id' => $prediction->id,

                    'symbol' => $company?->symbol,

                    'company' => Str::limit(
                        $company?->name ?? '',
                        20
                    ),

                    'index' =>
                        $company?->index_name
                        ?? $company?->market_index
                        ?? $company?->listed_index
                        ?? $company?->exchange_index
                        ?? $company?->index,

                    'signal' => strtoupper(
                        $prediction->signal
                        ?? $prediction->direction
                        ?? 'HOLD'
                    ),

                    'score' => min(
                        round((float)$prediction->prediction_score),
                        98
                    ),

                    'prediction_time' =>
                        $prediction->prediction_date
                        ?? $prediction->created_at,

                    'price' => $quote['price'] ?? null,

                    'currency' => $quote['currency'] ?? '',

                    'change' => $quote['change_percent'] ?? null,

                    'sparkline' => $sparkline,

                    'run_type' => ucfirst(
                        $lastRun?->type ?? ''
                    ),

                    'run_status' => ucfirst(
                        $lastRun?->status ?? ''
                    ),

                    'models' => $models,

                ];

            })
            ->toArray();
    }
}
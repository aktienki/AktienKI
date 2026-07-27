<?php

namespace App\Repositories;

use App\Models\Prediction;
use Illuminate\Database\Eloquent\Collection;

class PredictionRepository
{
    public function latestTopBuys(int $limit = 10): Collection
    {
        return Prediction::query()
            ->with(['company.profile', 'models'])
            ->latestDate()
            ->buy()
            ->orderByDesc('prediction_score')
            ->orderByDesc('expected_return')
            ->limit($limit)
            ->get();
    }

    public function latestForCompany(int $companyId, int $limit = 30): Collection
    {
        return Prediction::query()
            ->where('company_id', $companyId)
            ->with('models')
            ->orderByDesc('prediction_date')
            ->limit($limit)
            ->get();
    }

    public function latestBySignal(string $signal, int $limit = 25): Collection
    {
        return Prediction::query()
            ->with('company')
            ->latestDate()
            ->signal($signal)
            ->orderByDesc('prediction_score')
            ->limit($limit)
            ->get();
    }
}

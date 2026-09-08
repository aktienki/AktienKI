<?php

namespace App\Services;

use App\Enums\PlanLevel;
use App\Models\User;
use App\Models\UserTradeOpportunity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TradeOpportunityService
{
    public function __construct(
        private readonly PersonalizedSignalService $signals,
        private readonly PlanAccessService $plans,
    ) {}

    public function purgeExpired(): int
    {
        return UserTradeOpportunity::query()->where('expires_at', '<=', now())->delete();
    }

    public function syncForUser(User $user): int
    {
        if (! $this->plans->allowsTariff($user, PlanLevel::Pro)) return 0;

        UserTradeOpportunity::query()->where('user_id', $user->id)->where('expires_at', '<=', now())->delete();
        $signalSql = $this->signals->sql('prediction', $user);
        $rows = DB::table('predictions as prediction')
            ->join('instruments as instrument', 'instrument.id', '=', 'prediction.instrument_id')
            ->where('instrument.type', 'stock')->where('instrument.is_active', true)->whereNull('instrument.deleted_at')
            ->where('prediction.quality_gate_passed', true)
            ->whereRaw('prediction.id = (SELECT latest.id FROM predictions latest WHERE latest.instrument_id = prediction.instrument_id ORDER BY latest.prediction_time DESC NULLS LAST, latest.id DESC LIMIT 1)')
            ->select(['prediction.*', 'instrument.symbol', 'instrument.name', 'instrument.currency'])
            ->selectRaw("{$signalSql} AS personalized_signal")
            ->get();

        $created = 0;
        foreach ($rows as $row) {
            if (! in_array(strtoupper((string) $row->personalized_signal), ['WAIT', 'HOLD'], true)) continue;
            $price = is_numeric($row->current_price) ? (float) $row->current_price : null;
            if ($price === null || $price <= 0) continue;

            $returns = [];
            foreach ([5, 10, 15, 20] as $days) {
                $field = "predicted_price_{$days}d";
                $target = is_numeric($row->{$field} ?? null) ? (float) $row->{$field} : null;
                if ($target === null) {
                    $target = DB::table('predictions')->where('instrument_id', $row->instrument_id)
                        ->where('prediction_horizon_minutes', $days * 1440)->whereNotNull($field)
                        ->orderByDesc('prediction_time')->orderByDesc('id')->value($field);
                    $target = is_numeric($target) ? (float) $target : null;
                }
                $returns[$days] = $target === null ? null : (($target - $price) / $price) * 100;
            }

            $shortNegative = collect([5, 10, 15])->filter(fn (int $days): bool => is_numeric($returns[$days]) && $returns[$days] < 0)->count();
            $score = is_numeric($row->prediction_score) ? (float) $row->prediction_score : null;
            $score = $score === null ? null : ($score <= 1 ? $score * 100 : ($score <= 10 ? $score * 10 : $score));
            $confidence = is_numeric($row->confidence) ? (float) $row->confidence : null;
            $confidence = $confidence === null ? null : ($confidence <= 1 ? $confidence * 100 : $confidence);
            $riskSource = is_numeric($row->risk_score) ? (float) $row->risk_score : (is_numeric($row->drawdown_risk_factor) ? (float) $row->drawdown_risk_factor : null);
            $risk = $riskSource === null ? null : ($riskSource <= 1 ? $riskSource * 100 : $riskSource);
            if ($shortNegative < 2 || ! is_numeric($returns[20]) || $returns[20] < 1.0) continue;
            if ($score === null || $score < 40 || $confidence === null || $confidence < 40 || $risk === null || $risk > 50) continue;

            $detectedAt = CarbonImmutable::parse($row->prediction_time ?: now());
            $expiresAt = $detectedAt->addWeekdays(20)->endOfDay();
            if ($expiresAt->isPast()) continue;

            $opportunity = UserTradeOpportunity::query()->firstOrNew(['user_id' => $user->id, 'instrument_id' => $row->instrument_id]);
            $predictionChanged = $opportunity->exists && (int) $opportunity->prediction_id !== (int) $row->id;
            $opportunity->fill([
                'prediction_id' => $row->id, 'detected_at' => $detectedAt, 'expires_at' => $expiresAt,
                'snapshot' => [
                    'symbol' => $row->symbol, 'name' => $row->name, 'currency' => $row->currency,
                    'price' => $price, 'score' => $score, 'confidence' => $confidence,
                    'risk' => $risk, 'returns' => $returns,
                ],
            ]);
            if (! $opportunity->exists || $predictionChanged) {
                $opportunity->fill(['status' => 'open', 'viewed_at' => null, 'completed_at' => null]);
            }
            $opportunity->save();
            $created++;
        }

        return $created;
    }
}

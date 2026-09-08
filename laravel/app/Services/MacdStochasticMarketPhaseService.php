<?php

namespace App\Services;

use App\Models\Prediction;
use Illuminate\Support\Facades\DB;

final class MacdStochasticMarketPhaseService
{
    /** @return array{key:string,label:string,score_adjustment:float,buy_veto:bool}|null */
    public function forPrediction(Prediction $prediction): ?array
    {
        $rows = DB::table('technical_indicators')
            ->where('instrument_id', $prediction->instrument_id)
            ->where('interval', '1d')
            ->when($prediction->prediction_time, fn ($query) => $query->where('bar_time', '<=', $prediction->prediction_time))
            ->whereNotNull('macd_histogram')
            ->whereNotNull('stochastic_k')
            ->orderByDesc('bar_time')
            ->limit(2)
            ->get(['macd_histogram', 'stochastic_k']);

        if ($rows->count() < 2) {
            return null;
        }

        return $this->classify(
            (float) $rows[0]->macd_histogram,
            (float) $rows[1]->macd_histogram,
            (float) $rows[0]->stochastic_k,
            (float) $rows[1]->stochastic_k,
        );
    }

    /** @return array{key:string,label:string,score_adjustment:float,buy_veto:bool} */
    public function classify(float $macd, float $previousMacd, float $stochastic, float $previousStochastic): array
    {
        $macdRising = $macd > $previousMacd;
        $stochasticRising = $stochastic > $previousStochastic;

        return match (true) {
            $macd >= 0 && $macdRising && $stochastic >= 50 && $stochastic < 80
                => $this->phase('bullish_impulse', 'Bullischer Impuls', 4),
            $macd >= 0 && ! $macdRising && $stochastic >= 80
                => $this->phase('overheated_fading', 'Überhitzt / nachlassend', -12, true),
            $macdRising && $stochasticRising && $stochastic < 50
                => $this->phase('early_recovery', 'Frühe Erholung', 7),
            $macd < 0 && ! $macdRising && $stochastic < 50
                => $this->phase('bearish_impulse', 'Bärischer Impuls / Rebound-Fenster', 3),
            $stochastic < 20 && $macdRising
                => $this->phase('oversold_stabilizing', 'Überverkauft / Stabilisierung', 8),
            $macd >= 0 && $stochastic >= 80
                => $this->phase('mature_uptrend', 'Starker reifer Trend', 0),
            $macd < 0 && $stochastic >= 50
                => $this->phase('negative_divergence', 'Negative Divergenz / Erholungschance', 8),
            default => $this->phase('neutral_transition', 'Neutral / Übergang', 0),
        };
    }

    /** @return array{key:string,label:string,score_adjustment:float,buy_veto:bool} */
    private function phase(string $key, string $label, float $adjustment, bool $buyVeto = false): array
    {
        return ['key' => $key, 'label' => $label, 'score_adjustment' => $adjustment, 'buy_veto' => $buyVeto];
    }
}

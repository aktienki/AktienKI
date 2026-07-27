<?php

// app/Services/MarketService.php

namespace App\Services;

use App\Support\AiScore;

class MarketService
{
    public function overallAssessment(array $markets, array $dailyAiScores, string $riskLevel = 'normal'): array
    {
        $changes = collect($markets)
            ->pluck('change')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value);
        $volatilities = collect($markets)
            ->pluck('volatility')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value);

        $averageChange = (float) ($changes->avg() ?? 0);
        $averageVolatility = (float) ($volatilities->avg() ?? 0);
        $latestAiScore = (float) (collect($dailyAiScores)->last()['y'] ?? 5);
        $marketScore = max(0, min(10, 5 + ($averageChange * 1.5)));
        $score = round(($latestAiScore * 0.65) + ($marketScore * 0.35), 1);

        if ($averageVolatility >= 1) {
            $score = round(max(0, $score - 0.5), 1);
        }

        [$status, $tone] = match (true) {
            $score >= 6.5 => ['Positiv', 'positive'],
            $score >= 4.5 => ['Neutral', 'neutral'],
            default => ['Vorsichtig', 'cautious'],
        };

        $positiveMarkets = $changes->filter(fn ($value) => $value >= 0)->count();
        $locale = app()->getLocale();
        $riskNames = $locale === 'en'
            ? ['cautious' => 'cautious', 'normal' => 'balanced', 'offensive' => 'offensive']
            : ['cautious' => 'vorsichtig', 'normal' => 'ausgewogen', 'offensive' => 'offensiv'];
        $riskName = $riskNames[$riskLevel] ?? $riskNames['normal'];

        $summary = $locale === 'en'
            ? sprintf(
                '%d of %d tracked markets are rising. Average hourly volatility is %.2f%%. The assessment is aligned with your %s risk profile.',
                $positiveMarkets,
                $changes->count(),
                $averageVolatility,
                $riskName,
            )
            : sprintf(
                '%d von %d beobachteten Märkten steigen. Die durchschnittliche Stundenvolatilität liegt bei %.2f %%. Die Einordnung berücksichtigt dein %s Risikoprofil.',
                $positiveMarkets,
                $changes->count(),
                $averageVolatility,
                $riskName,
            );

        if ($locale === 'en') {
            $status = match ($tone) {
                'positive' => 'Positive',
                'neutral' => 'Neutral',
                default => 'Cautious',
            };
        }

        $marketCount = $changes->count();

        return compact(
            'score',
            'status',
            'tone',
            'summary',
            'averageChange',
            'averageVolatility',
            'positiveMarkets',
            'marketCount',
            'riskName',
        );
    }

    public function marketSituations(array $markets, array $indexAiScores = []): array
    {
        return collect($markets)->map(function (array $market) use ($indexAiScores) {
            $change = (float) ($market['change'] ?? 0);
            $volatility = $this->hourlyVolatility($market['candles'] ?? []);
            $scoreData = $indexAiScores[$market['name']] ?? null;
            $aiScore = AiScore::toPercent($scoreData['score'] ?? null);

            $volatilityLabel = match (true) {
                $volatility >= 0.8 => 'Hoch',
                $volatility >= 0.35 => 'Mittel',
                default => 'Niedrig',
            };

            return [
                'title' => $market['name'],
                'trend' => $this->trend($change),
                'change' => $change,
                'volatility' => $volatility,
                'volatility_label' => $volatilityLabel,
                'ai_score' => $aiScore,
                'ai_companies' => (int) ($scoreData['companies'] ?? 0),
            ];
        })->all();
    }

    public function sentiment(array $markets): array
    {
        $dax = $this->change($markets, 'DAX');
        $nasdaq = $this->change($markets, 'NASDAQ');
        $sp500 = $this->change($markets, 'S&P 500');
        $gold = $this->change($markets, 'Gold');
        $oil = $this->change($markets, 'Öl');

        $marketScore =
            (($dax ?? 0) * 0.25) +
            (($nasdaq ?? 0) * 0.25) +
            (($sp500 ?? 0) * 0.30) +
            (($gold ?? 0) * 0.10) +
            (($oil ?? 0) * 0.10);

        return [

            [
                'title'   => 'Trend',
                'value'   => $this->trend($marketScore),
                'status'  => number_format($marketScore,2,",",".") . '%',
                'percent' => min(100,max(0,50+$marketScore*8)),
                'color'   => $marketScore >= 0 ? 'green' : 'red',
            ],

            [
                'title'   => 'Momentum',
                'value'   => $this->momentum($marketScore),
                'status'  => $marketScore >= 0 ? 'Steigend' : 'Fallend',
                'percent' => min(100,max(0,50+$marketScore*8)),
                'color'   => $marketScore >= 0 ? 'green' : 'red',
            ],

            [
                'title'   => 'Marktphase',
                'value'   => $this->phase($marketScore),
                'status'  => 'AI Analyse',
                'percent' => min(100,max(0,50+$marketScore*8)),
                'color'   => $marketScore >= 0 ? 'violet' : 'amber',
            ],

            [
                'title'   => 'Volatilität',
                'value'   => abs($marketScore) > 1.5 ? 'Hoch' : 'Normal',
                'status'  => 'Markt',
                'percent' => min(100,max(15,abs($marketScore)*25)),
                'color'   => abs($marketScore) > 1.5 ? 'amber' : 'green',
            ],

            [
                'title'   => 'Market Score',
                'value'   => number_format($marketScore,2,",","."),
                'status'  => 'Composite',
                'percent' => min(100,max(0,50+$marketScore*8)),
                'color'   => $marketScore >= 0 ? 'green' : 'red',
            ],

        ];
    }

    private function change(array $markets,string $name): ?float
    {
        foreach($markets as $market){

            if($market['name']===$name){

                return $market['change'];

            }

        }

        return null;
    }

    private function trend(float $score): string
    {
        return match(true){
            $score>1.5 => 'Strong Bull',
            $score>0.5 => 'Bullish',
            $score>-0.5 => 'Neutral',
            $score>-1.5 => 'Bearish',
            default => 'Strong Bear',
        };
    }

    private function momentum(float $score): string
    {
        return match(true){
            $score>1.5 => 'Sehr Stark',
            $score>0.5 => 'Positiv',
            $score>-0.5 => 'Seitwärts',
            $score>-1.5 => 'Negativ',
            default => 'Sehr Schwach',
        };
    }

    private function phase(float $score): string
    {
        return match(true){
            $score>1.5 => 'Risk On',
            $score>0.5 => 'Aufwärtstrend',
            $score>-0.5 => 'Konsolidierung',
            $score>-1.5 => 'Korrektur',
            default => 'Risk Off',
        };
    }

    private function hourlyVolatility(array $candles): float
    {
        $returns = collect($candles)
            ->map(function (array $candle) {
                $open = (float) ($candle['y'][0] ?? 0);
                $close = (float) ($candle['y'][3] ?? 0);

                return $open > 0 ? abs(($close - $open) / $open) * 100 : null;
            })
            ->filter(fn ($value) => $value !== null);

        return round((float) ($returns->avg() ?? 0), 2);
    }
}

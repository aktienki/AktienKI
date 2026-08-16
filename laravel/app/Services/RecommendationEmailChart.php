<?php

namespace App\Services;

final class RecommendationEmailChart
{
    public function render(array $candles, ?float $targetPrice): string
    {
        return $this->renderForecasts($candles, $targetPrice === null ? [] : [20 => $targetPrice]);
    }

    public function renderForecasts(array $candles, array $forecasts): string
    {
        $width = 720;
        $height = 300;
        $chart = imagecreatetruecolor($width, $height);
        imageantialias($chart, true);

        $background = imagecolorallocate($chart, 18, 32, 52);
        $grid = imagecolorallocatealpha($chart, 112, 138, 164, 103);
        $text = imagecolorallocate($chart, 164, 181, 201);
        $teal = imagecolorallocate($chart, 34, 211, 238);
        $red = imagecolorallocate($chart, 201, 92, 108);
        $amber = imagecolorallocate($chart, 224, 174, 84);
        $forecastFill = imagecolorallocatealpha($chart, 34, 211, 238, 95);
        imagefill($chart, 0, 0, $background);

        $bars = collect($candles)->filter(fn ($bar) => isset($bar['y']) && count($bar['y']) >= 4)->take(-32)->values();
        if ($bars->isEmpty()) {
            imagestring($chart, 5, 260, 140, 'Keine Kursdaten', $text);
            return $this->png($chart);
        }

        $prices = $bars->flatMap(fn ($bar) => array_map('floatval', $bar['y']))->all();
        $forecasts = collect($forecasts)->filter(fn ($price, $days) => is_numeric($days) && is_numeric($price))->mapWithKeys(fn ($price, $days) => [(int) $days => (float) $price])->sortKeys();
        foreach ($forecasts as $price) $prices[] = $price;
        $min = min($prices);
        $max = max($prices);
        $padding = max(($max - $min) * .13, max(abs($max), 1) * .015);
        $min -= $padding;
        $max += $padding;
        $plotLeft = 38;
        $plotTop = 24;
        $plotRight = 690;
        $plotBottom = 260;
        $forecastWidth = 250;
        $historyRight = $plotRight - $forecastWidth;
        $toY = fn (float $price): int => (int) round($plotBottom - (($price - $min) / max($max - $min, .00001)) * ($plotBottom - $plotTop));

        for ($i = 0; $i <= 4; $i++) {
            $y = (int) round($plotTop + (($plotBottom - $plotTop) / 4) * $i);
            imageline($chart, $plotLeft, $y, $plotRight, $y, $grid);
        }

        $step = ($historyRight - $plotLeft) / max($bars->count(), 1);
        $bodyWidth = max(3, min(8, (int) floor($step * .55)));
        foreach ($bars as $index => $bar) {
            [$open, $high, $low, $close] = array_map('floatval', $bar['y']);
            $x = (int) round($plotLeft + ($index + .5) * $step);
            $color = $close >= $open ? $teal : $red;
            imageline($chart, $x, $toY($high), $x, $toY($low), $color);
            imagefilledrectangle($chart, $x - intdiv($bodyWidth, 2), min($toY($open), $toY($close)), $x + intdiv($bodyWidth, 2), max($toY($open), $toY($close)) + 1, $color);
        }

        $current = (float) data_get($bars->last(), 'y.3');
        $startX = (int) round($historyRight - $step / 2);
        $startY = $toY($current);
        $previousX = $startX;
        $previousY = $startY;
        $maxDays = max(20, (int) ($forecasts->keys()->max() ?? 20));
        foreach ($forecasts as $days => $target) {
            $targetX = (int) round($historyRight + (($plotRight - $historyRight) * ($days / $maxDays)));
            $targetY = $toY($target);
            $positive = $target >= $current;
            $fill = $positive ? $forecastFill : imagecolorallocatealpha($chart, 201, 92, 108, 95);
            imagefilledpolygon($chart, [$previousX, $previousY, $targetX, $targetY - 12, $targetX, $targetY + 12], $fill);
            imagesetstyle($chart, [$amber, $amber, $amber, $background, $background]);
            imageline($chart, $previousX, $previousY, $targetX, $targetY, IMG_COLOR_STYLED);
            imageline($chart, $previousX, $previousY, $targetX, $targetY - 12, IMG_COLOR_STYLED);
            imageline($chart, $previousX, $previousY, $targetX, $targetY + 12, IMG_COLOR_STYLED);
            imagestring($chart, 2, max($historyRight, $targetX - 13), max($plotTop, $targetY - 27), $days.'T', $amber);
            $previousX = $targetX;
            $previousY = $targetY;
        }

        imagestring($chart, 3, $plotLeft, 274, 'Historie', $text);
        imagestring($chart, 3, $plotRight - 106, 274, '5 / 10 / 15 / 20 T', $amber);

        return $this->png($chart);
    }

    private function png(\GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 7);
        $png = (string) ob_get_clean();
        return $png;
    }
}

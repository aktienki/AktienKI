<?php

namespace App\Services;

final class SignalEmailDonutChart
{
    public function render(array $metrics, bool $darkTheme): string
    {
        $scale = 2;
        $width = 400;
        $height = 165;
        $image = imagecreatetruecolor($width * $scale, $height * $scale);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);
        imageantialias($image, true);

        $score = $metrics['score'] ?? null;
        $risk = $metrics['risk_percent'] ?? null;
        $this->drawDonut(
            $image,
            105 * $scale,
            67 * $scale,
            (int) ($metrics['score_level'] ?? 0),
            (string) ($metrics['score_grade'] ?? '—'),
            'KI-SCORE',
            is_numeric($score) ? number_format((float) $score, 1, ',', '.').' / 10' : '—',
            false,
            $darkTheme,
            $scale,
        );
        $this->drawDonut(
            $image,
            295 * $scale,
            67 * $scale,
            (int) ($metrics['risk_level'] ?? 0),
            is_numeric($metrics['risk_level'] ?? null) ? (string) $metrics['risk_level'] : '—',
            'RISIKO',
            is_numeric($risk) ? number_format((float) $risk, 0, ',', '.').' %' : '—',
            true,
            $darkTheme,
            $scale,
        );

        $output = imagecreatetruecolor($width, $height);
        imagesavealpha($output, true);
        imagealphablending($output, false);
        $outputTransparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
        imagefill($output, 0, 0, $outputTransparent);
        imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height, $width * $scale, $height * $scale);
        imagesavealpha($output, true);

        ob_start();
        imagepng($output, null, 8);
        $png = (string) ob_get_clean();
        imagedestroy($output);
        imagedestroy($image);

        return $png;
    }

    private function drawDonut(
        \GdImage $image,
        int $centerX,
        int $centerY,
        int $activeLevel,
        string $display,
        string $label,
        string $rawValue,
        bool $risk,
        bool $darkTheme,
        int $scale,
    ): void {
        $palette = $risk
            ? ['#35b779', '#8fca45', '#e1be32', '#ed8a32', '#df4d5f']
            : ['#df4d5f', '#ed8a32', '#e1be32', '#8fca45', '#35b779'];
        $inactive = $darkTheme ? '#40536a' : '#b5c5cb';
        $diameter = 96 * $scale;

        for ($index = 0; $index < 5; $index++) {
            $segment = $index + 1;
            $isActive = $segment <= $activeLevel;
            $isEnd = $segment === $activeLevel;
            $start = -90 + ($index * 67);
            $end = $start + 54;
            $color = $this->color($image, $isActive ? $palette[$index] : $inactive, $isActive ? 0 : 42);
            $thickness = ($isEnd ? 14 : ($isActive ? 9 : 6)) * $scale;

            if ($isEnd) {
                imagesetthickness($image, 20 * $scale);
                imagearc($image, $centerX, $centerY, $diameter, $diameter, $start, $end, $this->color($image, $palette[$index], 82));
            }
            imagesetthickness($image, $thickness);
            imagearc($image, $centerX, $centerY, $diameter, $diameter, $start, $end, $color);
        }

        $font = $this->font();
        $text = $this->color($image, $darkTheme ? '#e7edf5' : '#17263a');
        $muted = $this->color($image, $darkTheme ? '#9db0c5' : '#63758b');
        $this->centerText($image, $display, 22 * $scale, $centerX, $centerY, $text, $font);
        $this->centerText($image, $label, 11 * $scale, $centerX, 128 * $scale, $muted, $font);
        $this->centerText($image, $rawValue, 11 * $scale, $centerX, 148 * $scale, $text, $font);
    }

    private function centerText(\GdImage $image, string $value, int $size, int $centerX, int $centerY, int $color, ?string $font): void
    {
        if ($font !== null) {
            $box = imagettfbbox($size, 0, $font, $value);
            if ($box !== false) {
                $width = $box[2] - $box[0];
                $height = $box[1] - $box[7];
                imagettftext($image, $size, 0, (int) round($centerX - ($width / 2) - $box[0]), (int) round($centerY + ($height / 2)), $color, $font, $value);

                return;
            }
        }

        $fallback = $value === '—' ? '-' : $value;
        $fontId = 5;
        imagestring($image, $fontId, (int) round($centerX - (imagefontwidth($fontId) * strlen($fallback) / 2)), (int) round($centerY - imagefontheight($fontId) / 2), $fallback, $color);
    }

    private function color(\GdImage $image, string $hex, int $alpha = 0): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocatealpha(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            max(0, min(127, $alpha)),
        );
    }

    private function font(): ?string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ] as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        return null;
    }
}

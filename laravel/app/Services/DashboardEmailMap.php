<?php

namespace App\Services;

final class DashboardEmailMap
{
    public function render(array $countryChanges): string
    {
        $width = 720;
        $height = 360;
        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);
        $background = imagecolorallocate($image, 18, 32, 52);
        $neutral = imagecolorallocate($image, 29, 48, 70);
        $border = imagecolorallocate($image, 94, 119, 143);
        $teal = imagecolorallocate($image, 43, 163, 151);
        $tealBorder = imagecolorallocate($image, 82, 215, 196);
        $red = imagecolorallocate($image, 139, 82, 94);
        $redBorder = imagecolorallocate($image, 206, 124, 137);
        imagefill($image, 0, 0, $background);

        $geo = json_decode((string) file_get_contents(public_path('assets/ne_50m_admin_0_countries.geojson')), true);
        foreach (($geo['features'] ?? []) as $feature) {
            $code = strtoupper((string) ($feature['properties']['ISO_A2_EH'] ?? $feature['properties']['ISO_A2'] ?? ''));
            $change = isset($countryChanges[$code]) ? (float) $countryChanges[$code] : null;
            $fill = $change === null ? $neutral : ($change >= 0 ? $teal : $red);
            $stroke = $change === null ? $border : ($change >= 0 ? $tealBorder : $redBorder);
            $geometry = $feature['geometry'] ?? [];
            $polygons = ($geometry['type'] ?? '') === 'Polygon' ? [$geometry['coordinates'] ?? []] : ($geometry['coordinates'] ?? []);

            foreach ($polygons as $polygon) {
                foreach ($polygon as $ringIndex => $ring) {
                    $parts = $this->splitAtDateLine($ring);
                    foreach ($parts as $part) {
                        $points = [];
                        foreach ($part as [$lon, $lat]) {
                            $points[] = (int) round(((float) $lon + 180) / 360 * $width);
                            $points[] = (int) round((90 - (float) $lat) / 180 * $height);
                        }
                        if (count($points) < 6) continue;
                        if ($ringIndex === 0) imagefilledpolygon($image, $points, $fill);
                        imagepolygon($image, $points, $stroke);
                    }
                }
            }
        }

        ob_start();
        imagepng($image, null, 8);
        $png = (string) ob_get_clean();
        return $png;
    }

    private function splitAtDateLine(array $ring): array
    {
        $parts = [[]];
        $previous = null;
        foreach ($ring as $point) {
            if (! is_array($point) || count($point) < 2) continue;
            if ($previous !== null && abs((float) $point[0] - $previous) > 180) $parts[] = [];
            $parts[array_key_last($parts)][] = [(float) $point[0], (float) $point[1]];
            $previous = (float) $point[0];
        }
        return array_values(array_filter($parts, fn (array $part): bool => count($part) >= 3));
    }
}

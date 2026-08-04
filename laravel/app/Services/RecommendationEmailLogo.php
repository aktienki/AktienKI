<?php

namespace App\Services;

final class RecommendationEmailLogo
{
    public function render(): string
    {
        $image = imagecreatetruecolor(564, 104);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imageantialias($image, true);

        $panel = imagecolorallocate($image, 17, 16, 36);
        $violet = imagecolorallocate($image, 117, 98, 168);
        $lightViolet = imagecolorallocate($image, 216, 200, 244);
        $amber = imagecolorallocate($image, 230, 185, 93);
        $white = imagecolorallocate($image, 245, 243, 250);
        $muted = imagecolorallocate($image, 212, 208, 220);

        imagefilledrectangle($image, 2, 8, 146, 96, $panel);
        imagesetthickness($image, 3);
        imagerectangle($image, 4, 10, 144, 94, $violet);
        imagesetthickness($image, 6);
        imageline($image, 30, 69, 57, 38, $lightViolet);
        imageline($image, 57, 38, 82, 53, $lightViolet);
        imageline($image, 82, 53, 104, 27, $lightViolet);
        imagesetthickness($image, 3);
        imageline($image, 120, 25, 120, 79, $amber);
        imagefilledrectangle($image, 112, 40, 128, 67, $amber);

        $font = '/System/Library/Fonts/Supplemental/Arial Bold.ttf';
        if (is_file($font)) {
            imagettftext($image, 40, 0, 166, 70, $white, $font, 'aktien');
            imagettftext($image, 52, 0, 320, 76, $lightViolet, $font, 'KI');
            imagettftext($image, 32, 0, 401, 70, $muted, $font, '.com');
        } else {
            imagestring($image, 5, 166, 44, 'aktienKI.com', $white);
        }

        ob_start();
        imagepng($image, null, 8);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}

<?php

namespace App\Support;

final class CountryFlag
{
    public static function emoji(mixed $country): string
    {
        $code = strtoupper(trim((string) $country));

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '🌐';
        }

        return mb_chr(127397 + ord($code[0]), 'UTF-8')
            .mb_chr(127397 + ord($code[1]), 'UTF-8');
    }
}

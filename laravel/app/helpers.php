<?php

if (! function_exists('signal_label')) {
    /**
     * Legally-safe display label for a prediction/serving signal.
     *
     * The internal signal value stays 'BUY' / 'STRONG_BUY' everywhere that
     * matters technically - filters, URL query params, DB predicates, the
     * serving pipeline's recommended_signal column. AktienKI cannot show
     * "Buy"/"Kaufen" as a label because that reads as an investment
     * recommendation, so only the human-facing WORD is swapped here.
     * WATCH/HOLD/SELL/WAIT pass through unchanged.
     */
    function signal_label(?string $signal): string
    {
        return match (strtoupper((string) $signal)) {
            'BUY' => 'POSITIV',
            'STRONG_BUY' => 'STARK POSITIV',
            default => strtoupper((string) $signal),
        };
    }
}

if (! function_exists('signal_label_title_case')) {
    /**
     * Same mapping as signal_label(), for spots that display a capitalized
     * word (e.g. "Kaufen" -> "Positiv") instead of an all-caps badge.
     */
    function signal_label_title_case(?string $signal): string
    {
        return match (strtoupper((string) $signal)) {
            'BUY' => 'Positiv',
            'STRONG_BUY' => 'Stark positiv',
            'HOLD' => 'Halten',
            'SELL' => 'Verkaufen',
            'WATCH' => 'Beobachten',
            'WAIT' => 'Warten',
            default => (string) $signal,
        };
    }
}

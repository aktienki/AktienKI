<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExternalBuyReview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'triggered_at' => 'datetime',
        'positive_factors' => 'array',
        'risk_factors' => 'array',
        'key_findings' => 'array',
        'positive_factors_en' => 'array',
        'risk_factors_en' => 'array',
        'key_findings_en' => 'array',
        'research_limitations' => 'array',
        'sources' => 'array',
        'request_identity' => 'array',
        'signal_scope' => 'array',
        'usage' => 'array',
        'pricing_snapshot' => 'array',
        'raw_response' => 'array',
        'started_at' => 'datetime',
        'researched_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // These four transparently prefer the English translation
    // (TranslateAiTextToEnglish) whenever the current locale is English and
    // a translation already exists, falling back to the original German
    // text otherwise - every existing consumer (mail partial, stock page)
    // keeps reading $review->summary etc. unchanged.
    protected function summary(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes) => app()->getLocale() === 'en' && filled($attributes['summary_en'] ?? null)
            ? $attributes['summary_en']
            : $value);
    }

    protected function positiveFactors(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes) => app()->getLocale() === 'en' && filled($attributes['positive_factors_en'] ?? null)
            ? json_decode((string) $attributes['positive_factors_en'], true)
            : (is_array($value) ? $value : json_decode((string) $value, true)));
    }

    protected function riskFactors(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes) => app()->getLocale() === 'en' && filled($attributes['risk_factors_en'] ?? null)
            ? json_decode((string) $attributes['risk_factors_en'], true)
            : (is_array($value) ? $value : json_decode((string) $value, true)));
    }

    protected function keyFindings(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes) => app()->getLocale() === 'en' && filled($attributes['key_findings_en'] ?? null)
            ? json_decode((string) $attributes['key_findings_en'], true)
            : (is_array($value) ? $value : json_decode((string) $value, true)));
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }

    public function previousPrediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class, 'previous_prediction_id');
    }
}

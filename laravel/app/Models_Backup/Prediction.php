<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'prediction_date',
        'target_days',
        'current_price',
        'predicted_price',
        'expected_return',
        'buy_probability',
        'sell_probability',
        'prediction_score',
        'ensemble_score',
        'signal',
        'confidence',
        'meta',
    ];

    protected $casts = [
        'prediction_date' => 'date',
        'target_days' => 'integer',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function models(): HasMany
    {
        return $this->hasMany(PredictionModel::class);
    }

    public function scopeLatestDate(Builder $query): Builder
    {
        $latestDate = static::query()->max('prediction_date');

        return $latestDate
            ? $query->whereDate('prediction_date', $latestDate)
            : $query;
    }

    public function scopeSignal(Builder $query, string $signal): Builder
    {
        $signal = strtolower($signal);

        if (Schema::hasColumn('predictions', 'signal')) {
            return $query->whereRaw('LOWER(signal) = ?', [$signal]);
        }

        return match ($signal) {
            'buy' => $query->where(function (Builder $q): void {
                $q->whereColumn('buy_probability', '>=', 'sell_probability')
                    ->orWhere('expected_return', '>', 0);
            }),
            'sell' => $query->where(function (Builder $q): void {
                $q->whereColumn('sell_probability', '>', 'buy_probability')
                    ->orWhere('expected_return', '<', 0);
            }),
            default => $query,
        };
    }

    public function scopeBuy(Builder $query): Builder
    {
        return $query->signal('buy');
    }

    public function scopeSell(Builder $query): Builder
    {
        return $query->signal('sell');
    }

    public function scopeHold(Builder $query): Builder
    {
        if (Schema::hasColumn('predictions', 'signal')) {
            return $query->whereRaw('LOWER(signal) in (?, ?)', ['hold', 'neutral']);
        }

        return $query->where(function (Builder $q): void {
            $q->where(function (Builder $inner): void {
                $inner->whereNull('buy_probability')->whereNull('sell_probability');
            })->orWhere(function (Builder $inner): void {
                $inner->whereRaw('ABS(COALESCE(buy_probability, 0) - COALESCE(sell_probability, 0)) < 0.05')
                    ->whereRaw('ABS(COALESCE(expected_return, 0)) < 0.01');
            });
        });
    }

    public function getAiScoreAttribute(): int
    {
        $score = $this->prediction_score ?? $this->prediction_score ?? 0;

        return (int) max(0, min(100, round((float) $score)));
    }

    public function getDirectionAttribute(): string
    {
        if (array_key_exists('signal', $this->attributes) && filled($this->attributes['signal'] ?? null)) {
            return strtoupper((string) $this->attributes['signal']);
        }

        $buy = (float) ($this->buy_probability ?? 0);
        $sell = (float) ($this->sell_probability ?? 0);
        $return = (float) ($this->expected_return ?? 0);

        if ($buy > $sell && $return >= 0) {
            return 'BUY';
        }

        if ($sell > $buy && $return < 0) {
            return 'SELL';
        }

        if ($buy > $sell) {
            return 'BUY';
        }

        if ($sell > $buy) {
            return 'SELL';
        }

        return 'HOLD';
    }

    public function getBestModelAttribute(): ?string
    {
        if ($this->relationLoaded('models')) {
            $models = $this->models;

            if (Schema::hasColumn('prediction_models', 'rank')) {
                return $models->sortBy([['rank', 'asc'], ['score', 'desc']])->first()?->model;
            }

            return $models->sortByDesc('score')->first()?->model;
        }

        $query = $this->models();

        if (Schema::hasColumn('prediction_models', 'rank')) {
            $query->orderBy('rank');
        }

        return $query->orderByDesc('score')->first()?->model;
    }

    public function getRiskAttribute(): float
    {
        $confidence = (float) ($this->confidence ?? 0);

        if ($confidence > 0 && $confidence <= 1) {
            $confidence *= 100;
        }

        return round(max(0, min(100, 100 - $confidence)), 1);
    }

    public function getTrendAttribute(): string
    {
        $return = (float) ($this->expected_return ?? 0);

        return match (true) {
            $this->direction === 'BUY' && $return > 0 => 'Bullish',
            $this->direction === 'SELL' && $return < 0 => 'Bearish',
            default => 'Neutral',
        };
    }
}

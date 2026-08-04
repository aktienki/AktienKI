<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentFundamental extends Model
{
    protected $guarded = [];

    protected $casts = [
        'snapshot_date' => 'date',
        'fiscal_date' => 'date',
        'reported_at' => 'datetime',
        'retrieved_at' => 'datetime',
        'data' => 'array',
        'raw_data' => 'array',
        'market_cap' => 'float',
        'enterprise_value' => 'float',
        'trailing_pe' => 'float',
        'forward_pe' => 'float',
        'peg_ratio' => 'float',
        'price_to_book' => 'float',
        'price_to_sales' => 'float',
        'dividend_rate' => 'float',
        'dividend_yield' => 'float',
        'payout_ratio' => 'float',
        'profit_margin' => 'float',
        'operating_margin' => 'float',
        'return_on_assets' => 'float',
        'return_on_equity' => 'float',
        'revenue' => 'float',
        'revenue_growth' => 'float',
        'gross_profit' => 'float',
        'ebitda' => 'float',
        'net_income' => 'float',
        'total_cash' => 'float',
        'total_debt' => 'float',
        'debt_to_equity' => 'float',
        'current_ratio' => 'float',
        'quick_ratio' => 'float',
        'operating_cash_flow' => 'float',
        'free_cash_flow' => 'float',
        'shares_outstanding' => 'float',
        'float_shares' => 'float',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}

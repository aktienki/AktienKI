<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PortfolioCashLedger extends Model
{
    protected $table = 'portfolio_cash_ledger';
    protected $guarded = [];
    protected $casts = ['amount' => 'float', 'balance_after' => 'float', 'occurred_at' => 'datetime', 'meta' => 'array'];
    public function account(): BelongsTo { return $this->belongsTo(PortfolioCashAccount::class, 'portfolio_cash_account_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(PortfolioTransaction::class, 'portfolio_transaction_id'); }
}

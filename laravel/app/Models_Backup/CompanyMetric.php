<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','date','market_cap','pe','forward_pe','pb','ps','roe','roa','current_ratio','quick_ratio',
        'debt_equity','gross_margin','operating_margin','profit_margin','free_cashflow','eps','book_value',
        'revenue_growth','earnings_growth',
    ];

    protected $casts = ['date' => 'date'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}

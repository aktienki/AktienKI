<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','description','website','logo_url','employees','ceo','headquarter','founded','market_cap',
        'enterprise_value','shares_outstanding','float_shares','beta','dividend_yield','last_update',
    ];

    protected $casts = [
        'employees' => 'integer',
        'market_cap' => 'decimal:4',
        'enterprise_value' => 'decimal:4',
        'shares_outstanding' => 'decimal:4',
        'float_shares' => 'decimal:4',
        'beta' => 'decimal:6',
        'dividend_yield' => 'decimal:6',
        'last_update' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}

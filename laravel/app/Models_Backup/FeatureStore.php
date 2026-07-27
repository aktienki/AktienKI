<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureStore extends Model
{
    use HasFactory;

    protected $table = 'feature_store';
    protected $fillable = ['company_id','date','feature','value','meta'];
    protected $casts = ['date' => 'date', 'meta' => 'array'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}

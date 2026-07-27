<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainedModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','model','version','training_date','samples','features','accuracy','mae','rmse','r2','precision','recall',
        'f1','path','status','active','meta',
    ];

    protected $casts = [
        'training_date' => 'datetime', 'samples' => 'integer', 'features' => 'array', 'active' => 'boolean', 'meta' => 'array',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function scopeActive(Builder $query): Builder { return $query->where('active', true)->where('status', 'active'); }
}

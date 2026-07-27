<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class News extends Model
{
    use HasFactory;

    protected $table = 'news';
    protected $fillable = ['source','title','summary','url','published_at','sentiment_score','language','meta'];
    protected $casts = ['published_at' => 'datetime', 'meta' => 'array'];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_news')->withTimestamps();
    }
}

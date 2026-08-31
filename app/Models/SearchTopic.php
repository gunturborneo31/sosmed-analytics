<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['keyword', 'description', 'time_filter_type', 'start_date', 'end_date', 'is_active'])]
class SearchTopic extends Model
{
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];

    public function articles(): HasMany
    {
        return $this->hasMany(ScrapedArticle::class);
    }

    public function scrapedArticles(): BelongsToMany
    {
        return $this->belongsToMany(ScrapedArticle::class, 'scraped_article_topic');
    }
}
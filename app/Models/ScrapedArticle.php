<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['media_source_id', 'search_topic_id', 'title', 'article_url', 'summary', 'published_at'])]
class ScrapedArticle extends Model
{
    protected $casts = ['published_at' => 'datetime'];

    public function mediaSource(): BelongsTo
    {
        return $this->belongsTo(MediaSource::class);
    }

    public function searchTopic(): BelongsTo
    {
        return $this->belongsTo(SearchTopic::class);
    }

    public function searchTopics(): BelongsToMany
    {
        return $this->belongsToMany(SearchTopic::class, 'scraped_article_topic');
    }
}
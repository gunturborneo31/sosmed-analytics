<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'base_url',
    'feed_url',
    'is_active',
    'can_read_view_count',
    'can_read_visitor_count',
    'metrics_checked_at',
    'metrics_check_message',
])]
class MediaSource extends Model
{
    protected $casts = [
        'is_active' => 'boolean',
        'can_read_view_count' => 'boolean',
        'can_read_visitor_count' => 'boolean',
        'metrics_checked_at' => 'datetime',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(ScrapedArticle::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'base_url', 'feed_url', 'is_active'])]
class MediaSource extends Model
{
    protected $casts = ['is_active' => 'boolean'];

    public function articles(): HasMany
    {
        return $this->hasMany(ScrapedArticle::class);
    }
}
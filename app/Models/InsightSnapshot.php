<?php

namespace App\Models;

use Database\Factories\InsightSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'social_account_id', 'snapshot_date', 'followers_count', 'reach',
    'impressions', 'profile_views', 'interactions', 'engagement_rate', 'raw_payload',
])]
class InsightSnapshot extends Model
{
    /** @use HasFactory<InsightSnapshotFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'followers_count' => 'integer',
            'reach' => 'integer',
            'impressions' => 'integer',
            'profile_views' => 'integer',
            'interactions' => 'integer',
            'engagement_rate' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }

    /** @return BelongsTo<SocialAccount, $this> */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /** @param Builder<$this> $query */
    public function scopeBetween(Builder $query, string $from, string $until): void
    {
        $query->whereBetween('snapshot_date', [$from, $until]);
    }

    /** @param Builder<$this> $query */
    public function scopeLastDays(Builder $query, int $days): void
    {
        $query->where('snapshot_date', '>=', now()->subDays($days)->toDateString());
    }
}

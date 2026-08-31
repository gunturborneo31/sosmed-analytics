<?php

namespace App\Models;

use Database\Factories\SyncLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['social_account_id', 'status', 'trigger', 'message', 'duration_ms'])]
class SyncLog extends Model
{
    /** @use HasFactory<SyncLogFactory> */
    use HasFactory, HasUuids;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PARTIAL = 'partial';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<SocialAccount, $this> */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /** @param Builder<$this> $query */
    public function scopeFailed(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_PARTIAL]);
    }
}

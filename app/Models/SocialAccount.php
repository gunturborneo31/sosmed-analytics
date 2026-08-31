<?php

namespace App\Models;

use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organizational_unit_id', 'connected_by', 'platform', 'auth_source', 'platform_account_id',
    'username', 'display_name', 'avatar_url', 'access_token',
    'token_expires_at', 'status', 'last_synced_at',
])]
#[Hidden(['access_token'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory, HasUuids;

    public const PLATFORM_INSTAGRAM = 'instagram';

    public const PLATFORM_FACEBOOK = 'facebook';

    /** Jalur otorisasi — menentukan server API mana yang dipakai. */
    public const AUTH_FACEBOOK = 'facebook';

    public const AUTH_INSTAGRAM = 'instagram';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_ERROR = 'error';

    /**
     * Token akses tidak pernah disimpan sebagai teks biasa (§12).
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /** @return HasMany<InsightSnapshot, $this> */
    public function insightSnapshots(): HasMany
    {
        return $this->hasMany(InsightSnapshot::class);
    }

    /** @return HasMany<AudienceBreakdown, $this> */
    public function audienceBreakdowns(): HasMany
    {
        return $this->hasMany(AudienceBreakdown::class);
    }

    /** @return HasMany<SyncLog, $this> */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function latestSnapshot(): ?InsightSnapshot
    {
        return $this->insightSnapshots()->latest('snapshot_date')->first();
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_CONNECTED);
    }

    /** @param Builder<$this> $query */
    public function scopePlatform(Builder $query, string $platform): void
    {
        $query->where('platform', $platform);
    }

    /**
     * Token yang kedaluwarsa dalam N hari ke depan — bahan RefreshExpiringTokens.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->whereNotNull('token_expires_at')
            ->whereBetween('token_expires_at', [now(), now()->addDays($days)]);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}

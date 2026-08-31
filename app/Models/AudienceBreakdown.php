<?php

namespace App\Models;

use Database\Factories\AudienceBreakdownFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['social_account_id', 'snapshot_date', 'dimension', 'data'])]
class AudienceBreakdown extends Model
{
    /** @use HasFactory<AudienceBreakdownFactory> */
    use HasFactory, HasUuids;

    public const DIMENSION_AGE = 'age';

    public const DIMENSION_GENDER = 'gender';

    public const DIMENSION_AGE_GENDER = 'age_gender';

    public const DIMENSION_CITY = 'city';

    public const DIMENSION_COUNTRY = 'country';

    public const DIMENSION_LOCALE = 'locale';

    public const DIMENSIONS = [
        self::DIMENSION_AGE,
        self::DIMENSION_GENDER,
        self::DIMENSION_AGE_GENDER,
        self::DIMENSION_CITY,
        self::DIMENSION_COUNTRY,
        self::DIMENSION_LOCALE,
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'data' => 'array',
        ];
    }

    /** @return BelongsTo<SocialAccount, $this> */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /** @param Builder<$this> $query */
    public function scopeDimension(Builder $query, string $dimension): void
    {
        $query->where('dimension', $dimension);
    }

    /** @param Builder<$this> $query */
    public function scopeOn(Builder $query, string $date): void
    {
        $query->where('snapshot_date', $date);
    }
}

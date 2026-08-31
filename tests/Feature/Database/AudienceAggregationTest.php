<?php

use App\Models\AudienceBreakdown;
use App\Models\SocialAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregasi demografi lintas OPD (§10.1) — inti fitur rekap admin.
 */
function agregatUmur(string $date): Collection
{
    return DB::table('audience_breakdowns as ab')
        ->crossJoin(DB::raw('LATERAL jsonb_each_text(ab.data) AS kv(key, value)'))
        ->where('ab.dimension', AudienceBreakdown::DIMENSION_AGE)
        ->where('ab.snapshot_date', $date)
        ->groupBy('kv.key')
        ->orderBy('kv.key')
        ->selectRaw('kv.key AS age_group, SUM(kv.value::bigint) AS total')
        ->pluck('total', 'age_group');
}

it('menjumlahkan kelompok umur dari beberapa akun jadi satu profil kabupaten', function () {
    $a = SocialAccount::factory()->create();
    $b = SocialAccount::factory()->create();

    AudienceBreakdown::factory()->on('2026-08-01')->create([
        'social_account_id' => $a->id,
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['18-24' => 1000, '25-34' => 2000],
    ]);

    AudienceBreakdown::factory()->on('2026-08-01')->create([
        'social_account_id' => $b->id,
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['18-24' => 500, '25-34' => 300, '35-44' => 700],
    ]);

    $agregat = agregatUmur('2026-08-01');

    expect((int) $agregat['18-24'])->toBe(1500)
        ->and((int) $agregat['25-34'])->toBe(2300)
        ->and((int) $agregat['35-44'])->toBe(700);
});

it('tidak mencampur data dari tanggal lain', function () {
    $account = SocialAccount::factory()->create();

    AudienceBreakdown::factory()->on('2026-08-01')->create([
        'social_account_id' => $account->id,
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['18-24' => 100],
    ]);
    AudienceBreakdown::factory()->on('2026-08-02')->create([
        'social_account_id' => $account->id,
        'dimension' => AudienceBreakdown::DIMENSION_AGE,
        'data' => ['18-24' => 999],
    ]);

    expect((int) agregatUmur('2026-08-01')['18-24'])->toBe(100);
});

it('menyimpan dan membaca kembali demografi sebagai array', function () {
    $breakdown = AudienceBreakdown::factory()->gender()->create();

    expect($breakdown->fresh()->data)->toBeArray()->toHaveKeys(['F', 'M', 'U']);
});

it('mencegah dimensi ganda untuk akun & tanggal yang sama', function () {
    $account = SocialAccount::factory()->create();

    AudienceBreakdown::factory()->on('2026-08-01')->age()->create(['social_account_id' => $account->id]);
    AudienceBreakdown::factory()->on('2026-08-01')->age()->create(['social_account_id' => $account->id]);
})->throws(UniqueConstraintViolationException::class);

it('bisa menyaring akun lewat query JSONB langsung', function () {
    $account = SocialAccount::factory()->create();

    AudienceBreakdown::factory()->on('2026-08-01')->create([
        'social_account_id' => $account->id,
        'dimension' => AudienceBreakdown::DIMENSION_CITY,
        'data' => ['Sangatta' => 4200, 'Bontang' => 900],
    ]);

    $hit = DB::table('audience_breakdowns')
        ->whereRaw('jsonb_exists(data, ?)', ['Sangatta'])
        ->count();

    expect($hit)->toBe(1);
});

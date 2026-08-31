<?php

use App\Models\AudienceBreakdown;
use App\Models\InsightSnapshot;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('membuat seluruh tabel inti', function () {
    foreach ([
        'organizational_units',
        'social_accounts',
        'insight_snapshots',
        'audience_breakdowns',
        'sync_logs',
        'roles',
        'permissions',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("tabel {$table} tidak ada");
    }
});

it('menautkan users ke perangkat daerah', function () {
    expect(Schema::hasColumn('users', 'organizational_unit_id'))->toBeTrue();
});

it('menyimpan kolom demografi & payload sebagai jsonb', function () {
    $types = DB::table('information_schema.columns')
        ->whereIn('table_name', ['audience_breakdowns', 'insight_snapshots'])
        ->whereIn('column_name', ['data', 'raw_payload'])
        ->pluck('data_type', 'column_name');

    expect($types['data'])->toBe('jsonb')
        ->and($types['raw_payload'])->toBe('jsonb');
});

it('memasang indeks GIN untuk query ke dalam JSONB', function () {
    $indexes = DB::table('pg_indexes')
        ->whereIn('indexname', ['idx_breakdowns_data', 'idx_snapshots_payload'])
        ->pluck('indexdef', 'indexname');

    expect($indexes)->toHaveCount(2)
        ->and($indexes['idx_breakdowns_data'])->toContain('gin')
        ->and($indexes['idx_snapshots_payload'])->toContain('gin');
});

it('menolak snapshot ganda pada tanggal yang sama', function () {
    $account = SocialAccount::factory()->create();

    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => '2026-08-01',
    ]);

    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => '2026-08-01',
    ]);
})->throws(UniqueConstraintViolationException::class);

it('menolak satu akun platform terdaftar di dua OPD', function () {
    SocialAccount::factory()->create([
        'platform' => 'instagram',
        'platform_account_id' => '17841400000000000',
    ]);

    SocialAccount::factory()->create([
        'platform' => 'instagram',
        'platform_account_id' => '17841400000000000',
    ]);
})->throws(UniqueConstraintViolationException::class);

it('menghapus data turunan saat akun medsos dihapus', function () {
    $account = SocialAccount::factory()->create();
    InsightSnapshot::factory()->create(['social_account_id' => $account->id]);
    AudienceBreakdown::factory()->create(['social_account_id' => $account->id]);
    SyncLog::factory()->create(['social_account_id' => $account->id]);

    $account->delete();

    expect(InsightSnapshot::count())->toBe(0)
        ->and(AudienceBreakdown::count())->toBe(0)
        ->and(SyncLog::count())->toBe(0);
});

<?php

use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\DB;

it('menyimpan access token dalam keadaan terenkripsi', function () {
    $account = SocialAccount::factory()->create(['access_token' => 'EAArahasia123']);

    $raw = DB::table('social_accounts')->where('id', $account->id)->value('access_token');

    expect($raw)->not->toBe('EAArahasia123')
        ->and($raw)->not->toContain('rahasia')
        ->and($account->fresh()->access_token)->toBe('EAArahasia123');
});

it('tidak membocorkan token saat model diserialisasi ke frontend', function () {
    $account = SocialAccount::factory()->create(['access_token' => 'EAArahasia123']);

    expect($account->toArray())->not->toHaveKey('access_token')
        ->and(json_encode($account))->not->toContain('rahasia');
});

it('menemukan token yang akan kedaluwarsa dalam 7 hari', function () {
    SocialAccount::factory()->expiring(3)->create();
    SocialAccount::factory()->create(['token_expires_at' => now()->addDays(45)]);
    SocialAccount::factory()->create(['token_expires_at' => null]);

    expect(SocialAccount::expiringWithin(7)->count())->toBe(1);
});

it('menandai token yang sudah lewat masa berlaku', function () {
    expect(SocialAccount::factory()->expired()->create()->isTokenExpired())->toBeTrue()
        ->and(SocialAccount::factory()->create()->isTokenExpired())->toBeFalse();
});

it('mengurutkan snapshot terbaru berdasarkan tanggal, bukan urutan input', function () {
    $account = SocialAccount::factory()->create();

    InsightSnapshot::factory()->on('2026-08-10')->create(['social_account_id' => $account->id, 'followers_count' => 100]);
    InsightSnapshot::factory()->on('2026-08-01')->create(['social_account_id' => $account->id, 'followers_count' => 50]);

    expect($account->latestSnapshot()->followers_count)->toBe(100);
});

it('mendaftar OPD yang belum punya akun terhubung', function () {
    $terhubung = OrganizationalUnit::factory()->create();
    SocialAccount::factory()->create(['organizational_unit_id' => $terhubung->id]);

    $dicabut = OrganizationalUnit::factory()->create();
    SocialAccount::factory()->create([
        'organizational_unit_id' => $dicabut->id,
        'status' => SocialAccount::STATUS_REVOKED,
    ]);

    $kosong = OrganizationalUnit::factory()->create();

    $slugs = OrganizationalUnit::unconnected()->pluck('slug');

    expect($slugs)->toContain($kosong->slug, $dicabut->slug)
        ->and($slugs)->not->toContain($terhubung->slug);
});

<?php

use App\Jobs\SyncSocialAccountInsights;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.meta.app_id' => '1234567890',
        'services.meta.app_secret' => 'rahasia-app',
        'services.meta.redirect' => 'http://localhost:8000/oauth/meta/callback',
        'services.meta.graph_version' => 'v21.0',
        'services.meta.graph_url' => 'https://graph.facebook.com/',
        'services.meta.config_id' => null,
    ]);

    $this->unit = OrganizationalUnit::factory()->create();
    $this->operator = operatorUser($this->unit);
});

it('mengirim operator ke dialog izin Meta dengan state tersimpan', function () {
    $response = $this->actingAs($this->operator)->get(route('oauth.meta.redirect'));

    $response->assertRedirectContains('facebook.com/v21.0/dialog/oauth');
    $response->assertRedirectContains('instagram_manage_insights');

    expect(session('meta_oauth_state'))->not->toBeEmpty()
        ->and(session('meta_oauth_unit'))->toBe($this->unit->id);
});

it('menolak berjalan bila kredensial Meta belum diisi', function () {
    config(['services.meta.app_id' => null, 'services.meta.app_secret' => null]);

    $this->actingAs($this->operator)
        ->get(route('oauth.meta.redirect'))
        ->assertRedirect(route('operator.accounts'))
        ->assertSessionHas('error');
});

it('menolak callback yang state-nya tidak cocok', function () {
    Http::fake();

    $this->actingAs($this->operator)
        ->withSession(['meta_oauth_state' => 'asli', 'meta_oauth_unit' => $this->unit->id])
        ->get(route('oauth.meta.callback', ['code' => 'abc', 'state' => 'palsu']))
        ->assertRedirect(route('operator.accounts'))
        ->assertSessionHas('error', 'Sesi otorisasi tidak cocok. Silakan ulangi dari awal.');

    Http::assertNothingSent();
    expect(SocialAccount::count())->toBe(0);
});

it('menyimpan Page dan akun IG tertaut, lalu mengantrekan sinkronisasi pertama', function () {
    Queue::fake();

    Http::fake([
        '*oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'token-pendek'])
            ->push(['access_token' => 'token-panjang', 'expires_in' => 5184000]),
        '*me/accounts*' => Http::response(['data' => [[
            'id' => '101010101',
            'name' => 'Kecamatan Sangatta Utara',
            'access_token' => 'token-page',
            'instagram_business_account' => [
                'id' => '17841400000000001',
                'username' => 'sangattautara',
                'name' => 'Kecamatan Sangatta Utara',
                'profile_picture_url' => 'https://cdn.example/ig.jpg',
            ],
        ]]]),
    ]);

    $this->actingAs($this->operator)
        ->withSession(['meta_oauth_state' => 'asli', 'meta_oauth_unit' => $this->unit->id])
        ->get(route('oauth.meta.callback', ['code' => 'kode-otorisasi', 'state' => 'asli']))
        ->assertRedirect(route('operator.accounts'))
        ->assertSessionHas('success');

    expect(SocialAccount::count())->toBe(2)
        ->and(SocialAccount::where('platform', 'instagram')->first()->username)->toBe('sangattautara')
        ->and(SocialAccount::first()->organizational_unit_id)->toBe($this->unit->id);

    Queue::assertPushed(SyncSocialAccountInsights::class, 2);
});

it('menyimpan token dalam keadaan terenkripsi, bukan teks biasa', function () {
    Queue::fake();

    Http::fake([
        '*oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'token-pendek'])
            ->push(['access_token' => 'token-panjang', 'expires_in' => 5184000]),
        '*me/accounts*' => Http::response(['data' => [[
            'id' => '101010101', 'name' => 'Page', 'access_token' => 'token-page-rahasia',
        ]]]),
    ]);

    $this->actingAs($this->operator)
        ->withSession(['meta_oauth_state' => 'asli', 'meta_oauth_unit' => $this->unit->id])
        ->get(route('oauth.meta.callback', ['code' => 'kode', 'state' => 'asli']));

    $raw = DB::table('social_accounts')->value('access_token');

    expect($raw)->not->toContain('token-page-rahasia')
        ->and(SocialAccount::first()->access_token)->toBe('token-page-rahasia');
});

it('memberi penjelasan yang bisa ditindaklanjuti saat tidak ada Page yang dikelola', function () {
    Http::fake([
        '*oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'token-pendek'])
            ->push(['access_token' => 'token-panjang', 'expires_in' => 5184000]),
        '*me/accounts*' => Http::response(['data' => []]),
    ]);

    $this->actingAs($this->operator)
        ->withSession([
            'meta_oauth_state' => 'asli',
            'meta_oauth_unit' => $this->unit->id,
            'meta_oauth_platform' => 'facebook',
        ])
        ->get(route('oauth.meta.callback', ['code' => 'kode', 'state' => 'asli']))
        ->assertRedirect(route('operator.accounts'));

    // Pesan gagal kini menyesuaikan platform yang dipilih operator.
    expect(session('error'))->toContain('Facebook Page')
        ->and(session('error'))->not->toContain('Business atau Creator');
});

it('menjelaskan syarat Instagram saat itu yang dipilih tapi tidak ada yang terhubung', function () {
    Http::fake([
        '*oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'token-pendek'])
            ->push(['access_token' => 'token-panjang', 'expires_in' => 5184000]),
        // Page ada, tapi tidak satu pun punya akun Instagram tertaut.
        '*me/accounts*' => Http::response(['data' => [[
            'id' => '101010101', 'name' => 'Page Tanpa IG', 'access_token' => 'token-page',
        ]]]),
    ]);

    $this->actingAs($this->operator)
        ->withSession([
            'meta_oauth_state' => 'asli',
            'meta_oauth_unit' => $this->unit->id,
            'meta_oauth_platform' => 'instagram',
        ])
        ->get(route('oauth.meta.callback', ['code' => 'kode', 'state' => 'asli']));

    expect(session('error'))->toContain('Business atau Creator')
        ->and(SocialAccount::count())->toBe(0);
});

it('menolak mengambil alih akun yang sudah dimiliki OPD lain', function () {
    $lain = OrganizationalUnit::factory()->create();
    SocialAccount::factory()->create([
        'organizational_unit_id' => $lain->id,
        'platform' => 'facebook',
        'platform_account_id' => '101010101',
    ]);

    Http::fake([
        '*oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'token-pendek'])
            ->push(['access_token' => 'token-panjang', 'expires_in' => 5184000]),
        '*me/accounts*' => Http::response(['data' => [[
            'id' => '101010101', 'name' => 'Page', 'access_token' => 'token-page',
        ]]]),
    ]);

    $this->actingAs($this->operator)
        ->withSession(['meta_oauth_state' => 'asli', 'meta_oauth_unit' => $this->unit->id])
        ->get(route('oauth.meta.callback', ['code' => 'kode', 'state' => 'asli']));

    expect(SocialAccount::count())->toBe(1)
        ->and(SocialAccount::first()->organizational_unit_id)->toBe($lain->id)
        ->and(session('error'))->toContain('perangkat daerah lain');
});

it('meneruskan penolakan izin dari Meta sebagai pesan, bukan error mentah', function () {
    $this->actingAs($this->operator)
        ->withSession(['meta_oauth_state' => 'asli', 'meta_oauth_unit' => $this->unit->id])
        ->get(route('oauth.meta.callback', [
            'state' => 'asli',
            'error' => 'access_denied',
            'error_description' => 'Pengguna membatalkan',
        ]))
        ->assertRedirect(route('operator.accounts'));

    expect(session('error'))->toContain('Izin dibatalkan');
});

it('melarang operator memutus akun milik OPD lain', function () {
    $lain = OrganizationalUnit::factory()->create();
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $lain->id]);

    $this->actingAs($this->operator)
        ->delete(route('oauth.meta.disconnect', $account))
        ->assertForbidden();

    expect(SocialAccount::whereKey($account->id)->exists())->toBeTrue();
});

it('mengizinkan operator memutus akun instansinya sendiri', function () {
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $this->unit->id]);

    $this->actingAs($this->operator)
        ->delete(route('oauth.meta.disconnect', $account))
        ->assertRedirect(route('operator.accounts'));

    expect(SocialAccount::whereKey($account->id)->exists())->toBeFalse();
});

it('meminta izin Instagram saja saat operator memilih Instagram', function () {
    $response = $this->actingAs($this->operator)
        ->get(route('oauth.meta.redirect', ['platform' => 'instagram']));

    $response->assertRedirectContains('instagram_manage_insights');
    // Izin khusus Facebook tidak ikut diminta — operator hanya menyetujui
    // apa yang benar-benar dipakai.
    expect($response->headers->get('Location'))
        ->not->toContain('pages_read_engagement')
        ->and(session('meta_oauth_platform'))->toBe('instagram');
});

it('meminta izin Facebook saja saat operator memilih Facebook', function () {
    $response = $this->actingAs($this->operator)
        ->get(route('oauth.meta.redirect', ['platform' => 'facebook']));

    $response->assertRedirectContains('pages_read_engagement');

    expect($response->headers->get('Location'))
        ->not->toContain('instagram_manage_insights')
        ->and(session('meta_oauth_platform'))->toBe('facebook');
});

it('mengabaikan platform yang tidak dikenali dan meminta seluruh izin', function () {
    $response = $this->actingAs($this->operator)
        ->get(route('oauth.meta.redirect', ['platform' => 'tiktok']));

    $response->assertRedirectContains('instagram_manage_insights')
        ->assertRedirectContains('pages_read_engagement');

    expect(session('meta_oauth_platform'))->toBeNull();
});

it('hanya menyimpan akun dari platform yang dipilih', function () {
    Queue::fake();

    Http::fake([
        '*oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'token-pendek'])
            ->push(['access_token' => 'token-panjang', 'expires_in' => 5184000]),
        // Meta mengembalikan Page beserta IG tertautnya dalam satu respons.
        '*me/accounts*' => Http::response(['data' => [[
            'id' => '101010101',
            'name' => 'Kecamatan Sangatta Utara',
            'access_token' => 'token-page',
            'instagram_business_account' => [
                'id' => '17841400000000001',
                'username' => 'sangattautara',
                'name' => 'Kecamatan Sangatta Utara',
            ],
        ]]]),
    ]);

    $this->actingAs($this->operator)
        ->withSession([
            'meta_oauth_state' => 'asli',
            'meta_oauth_unit' => $this->unit->id,
            'meta_oauth_platform' => 'instagram',
        ])
        ->get(route('oauth.meta.callback', ['code' => 'kode', 'state' => 'asli']))
        ->assertSessionHas('success');

    // Page-nya tidak ikut tersimpan meski ada di respons yang sama.
    expect(SocialAccount::count())->toBe(1)
        ->and(SocialAccount::first()->platform)->toBe('instagram')
        ->and(SocialAccount::first()->username)->toBe('sangattautara');
});

it('menyimpan keduanya saat platform tidak ditentukan', function () {
    Queue::fake();

    Http::fake([
        '*oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'token-pendek'])
            ->push(['access_token' => 'token-panjang', 'expires_in' => 5184000]),
        '*me/accounts*' => Http::response(['data' => [[
            'id' => '101010101',
            'name' => 'Page',
            'access_token' => 'token-page',
            'instagram_business_account' => ['id' => '178414000', 'username' => 'ig'],
        ]]]),
    ]);

    $this->actingAs($this->operator)
        ->withSession(['meta_oauth_state' => 'asli', 'meta_oauth_unit' => $this->unit->id])
        ->get(route('oauth.meta.callback', ['code' => 'kode', 'state' => 'asli']));

    expect(SocialAccount::pluck('platform')->sort()->values()->all())
        ->toBe(['facebook', 'instagram']);
});

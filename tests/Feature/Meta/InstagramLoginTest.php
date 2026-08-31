<?php

use App\Jobs\BackfillAccountHistory;
use App\Jobs\SyncSocialAccountInsights;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Services\Meta\InstagramInsightService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.meta.instagram_app_id' => '111222333',
        'services.meta.instagram_app_secret' => 'rahasia-instagram',
        'services.meta.instagram_redirect' => 'http://localhost:8000/oauth/instagram/callback',
        'services.meta.instagram_graph_url' => 'https://graph.instagram.com',
        'services.meta.instagram_auth_url' => 'https://www.instagram.com/oauth/authorize',
        'services.meta.instagram_token_url' => 'https://api.instagram.com/oauth/access_token',
    ]);

    $this->unit = OrganizationalUnit::factory()->create();
    $this->operator = operatorUser($this->unit);
});

/** Respons lengkap dari alur Instagram Login yang berhasil. */
function fakeInstagramLogin(array $profil = []): void
{
    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response([
            'access_token' => 'token-pendek-ig',
            'user_id' => '17841400000000009',
        ]),
        'graph.instagram.com/access_token*' => Http::response([
            'access_token' => 'token-panjang-ig',
            'expires_in' => 5184000,
        ]),
        'graph.instagram.com/me*' => Http::response($profil + [
            'user_id' => '17841400000000009',
            'username' => 'diskominfokutim',
            'name' => 'Diskominfo Kutai Timur',
            'profile_picture_url' => 'https://cdn.example/pp.jpg',
        ]),
    ]);
}

it('mengirim operator ke dialog izin Instagram, bukan Facebook', function () {
    $response = $this->actingAs($this->operator)->get(route('oauth.instagram.redirect'));

    $response->assertRedirectContains('instagram.com/oauth/authorize')
        ->assertRedirectContains('instagram_business_manage_insights');

    // Jalur ini tidak boleh menyentuh dialog Facebook sama sekali.
    expect($response->headers->get('Location'))->not->toContain('facebook.com')
        ->and(session('ig_oauth_state'))->not->toBeEmpty()
        ->and(session('ig_oauth_unit'))->toBe($this->unit->id);
});

it('menolak berjalan bila kredensial Instagram belum diisi', function () {
    config(['services.meta.instagram_app_id' => null, 'services.meta.instagram_app_secret' => null]);

    $this->actingAs($this->operator)
        ->get(route('oauth.instagram.redirect'))
        ->assertRedirect(route('operator.accounts'));

    expect(session('error'))->toContain('INSTAGRAM_APP_ID');
});

it('menolak callback yang state-nya tidak cocok', function () {
    Http::fake();

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'abc', 'state' => 'palsu']))
        ->assertRedirect(route('operator.accounts'));

    Http::assertNothingSent();
    expect(SocialAccount::count())->toBe(0);
});

it('menyimpan akun Instagram tanpa perlu Facebook Page', function () {
    Queue::fake();
    fakeInstagramLogin();

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']))
        ->assertRedirect(route('operator.accounts'))
        ->assertSessionHas('success');

    $akun = SocialAccount::sole();

    expect($akun->platform)->toBe(SocialAccount::PLATFORM_INSTAGRAM)
        ->and($akun->username)->toBe('diskominfokutim')
        ->and($akun->organizational_unit_id)->toBe($this->unit->id)
        // Penanda jalur menentukan server API mana yang dipakai nanti.
        ->and($akun->auth_source)->toBe(SocialAccount::AUTH_INSTAGRAM);

    Queue::assertPushed(SyncSocialAccountInsights::class);
});

it('menukar token pendek jadi token panjang, bukan menyimpan yang pendek', function () {
    Queue::fake();
    fakeInstagramLogin();

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']));

    $akun = SocialAccount::sole();

    expect($akun->access_token)->toBe('token-panjang-ig')
        ->and($akun->token_expires_at->isAfter(now()->addDays(50)))->toBeTrue();
});

it('menyimpan token Instagram dalam keadaan terenkripsi', function () {
    Queue::fake();
    fakeInstagramLogin();

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']));

    expect(DB::table('social_accounts')->value('access_token'))
        ->not->toContain('token-panjang-ig');
});

it('menolak mengambil alih akun Instagram milik OPD lain', function () {
    Queue::fake();
    fakeInstagramLogin();

    $lain = OrganizationalUnit::factory()->create();
    SocialAccount::factory()->create([
        'organizational_unit_id' => $lain->id,
        'platform' => SocialAccount::PLATFORM_INSTAGRAM,
        'platform_account_id' => '17841400000000009',
    ]);

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']));

    expect(SocialAccount::count())->toBe(1)
        ->and(SocialAccount::sole()->organizational_unit_id)->toBe($lain->id)
        ->and(session('error'))->toContain('perangkat daerah lain');
});

it('menjelaskan syarat Business/Creator saat Instagram tidak mengembalikan ID akun', function () {
    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'token-pendek-ig']),
        'graph.instagram.com/access_token*' => Http::response(['access_token' => 'token-panjang-ig', 'expires_in' => 5184000]),
        'graph.instagram.com/me*' => Http::response([]),
    ]);

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']));

    expect(session('error'))->toContain('Business atau Creator')
        ->and(SocialAccount::count())->toBe(0);
});

it('meneruskan penolakan Instagram sebagai pesan, bukan error mentah', function () {
    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response([
            'error_message' => 'Kode otorisasi sudah dipakai',
            'code' => 400,
        ], 400),
    ]);

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']))
        ->assertRedirect(route('operator.accounts'));

    // Pesannya diterjemahkan jadi langkah yang bisa dikerjakan operator.
    expect(session('error'))->toContain('Kode otorisasinya sudah terpakai')
        ->and(session('error'))->toContain('Mulai ulang')
        ->and(SocialAccount::count())->toBe(0);
});

it('memanggil server Instagram untuk akun dari jalur Instagram Login', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    $akun = SocialAccount::factory()->create([
        'platform' => SocialAccount::PLATFORM_INSTAGRAM,
        'auth_source' => SocialAccount::AUTH_INSTAGRAM,
    ]);

    app(InstagramInsightService::class)->dailyMetrics($akun, now());

    // Token pengguna Instagram hanya berlaku di graph.instagram.com —
    // dipakai di graph.facebook.com akan ditolak.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.instagram.com'));
});

it('tetap memanggil server Facebook untuk akun dari jalur Facebook Login', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    $akun = SocialAccount::factory()->create([
        'platform' => SocialAccount::PLATFORM_INSTAGRAM,
        'auth_source' => SocialAccount::AUTH_FACEBOOK,
    ]);

    app(InstagramInsightService::class)->dailyMetrics($akun, now());

    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
});

it('menerjemahkan galat peran developer jadi langkah yang bisa dikerjakan operator', function () {
    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response([
            'error_message' => 'Insufficient Developer Role: Insufficient developer role',
            'code' => 400,
        ], 400),
    ]);

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']));

    // Pesan mentah Meta ditulis untuk pengembang; operator OPD butuh langkahnya.
    expect(session('error'))->toContain('App roles')
        ->and(session('error'))->toContain('Instagram tester')
        ->and(session('error'))->toContain('Undangan penguji')
        ->and(session('error'))->not->toContain('Insufficient developer role');
});

it('meneruskan pesan asli Meta saat galatnya belum punya terjemahan', function () {
    Http::fake([
        'api.instagram.com/oauth/access_token' => Http::response([
            'error_message' => 'Sesuatu yang belum pernah kami temui',
            'code' => 400,
        ], 400),
    ]);

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']));

    // Pesan mentah tetap jauh lebih berguna daripada "terjadi kesalahan".
    expect(session('error'))->toContain('Sesuatu yang belum pernah kami temui');
});

it('menyimpan akun meski URL foto profil Instagram sangat panjang', function () {
    Queue::fake();

    // URL CDN Meta membawa tanda tangan, waktu kedaluwarsa, dan parameter
    // penelusuran — nyatanya bisa lewat 700 karakter, jauh di atas 255.
    $avatarPanjang = 'https://scontent-cgk2-1.cdninstagram.com/v/t51.82787-19/736494942_18604249318021958.jpg?'
        .http_build_query(['stp' => str_repeat('a', 300), 'oh' => str_repeat('b', 300), 'oe' => '6A8F14F1']);

    expect(mb_strlen($avatarPanjang))->toBeGreaterThan(255);

    fakeInstagramLogin(['profile_picture_url' => $avatarPanjang]);

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']))
        ->assertRedirect(route('operator.accounts'))
        ->assertSessionHas('success');

    expect(SocialAccount::sole()->avatar_url)->toBe($avatarPanjang);
});

it('mengantrekan sinkronisasi dan penarikan riwayat setelah otorisasi berhasil', function () {
    Queue::fake();
    fakeInstagramLogin();

    $this->actingAs($this->operator)
        ->withSession(['ig_oauth_state' => 'asli', 'ig_oauth_unit' => $this->unit->id])
        ->get(route('oauth.instagram.callback', ['code' => 'kode', 'state' => 'asli']))
        ->assertSessionHas('success');

    // Data hari kemarin ditarik segera, riwayatnya menyusul dalam satu pekerjaan
    // terpisah supaya saringan 7/30/90 hari langsung ada isinya.
    Queue::assertPushed(SyncSocialAccountInsights::class);
    Queue::assertPushed(BackfillAccountHistory::class);
});

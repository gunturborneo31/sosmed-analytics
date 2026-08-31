<?php

use App\Jobs\BackfillAccountHistory;
use App\Jobs\DispatchAllAccountSyncs;
use App\Jobs\RefreshExpiringTokens;
use App\Jobs\SyncSocialAccountInsights;
use App\Models\AudienceBreakdown;
use App\Models\InsightSnapshot;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use App\Services\Meta\FacebookInsightService;
use App\Services\Meta\InstagramInsightService;
use App\Services\Meta\InstagramLoginService;
use App\Services\Meta\MetaOAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.meta.app_id' => '123',
        'services.meta.app_secret' => 'rahasia',
        'services.meta.graph_version' => 'v21.0',
        'services.meta.graph_url' => 'https://graph.facebook.com/',
    ]);
});

/** Respons Graph API untuk akun Instagram yang sehat. */
function fakeInstagramGraph(): void
{
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'metric=follower_demographics') && str_contains($url, 'breakdown=age&')) {
            return Http::response(['data' => [['total_value' => ['breakdowns' => [['results' => [
                ['dimension_values' => ['18-24'], 'value' => 1200],
                ['dimension_values' => ['25-34'], 'value' => 3400],
            ]]]]]]]);
        }

        if (str_contains($url, 'metric=follower_demographics') && str_contains($url, 'breakdown=gender')) {
            return Http::response(['data' => [['total_value' => ['breakdowns' => [['results' => [
                ['dimension_values' => ['F'], 'value' => 2600],
                ['dimension_values' => ['M'], 'value' => 2000],
            ]]]]]]]);
        }

        if (str_contains($url, 'metric=follower_demographics') && str_contains($url, 'breakdown=city')) {
            return Http::response(['data' => [['total_value' => ['breakdowns' => [['results' => [
                ['dimension_values' => ['Sangatta'], 'value' => 3100],
            ]]]]]]]);
        }

        if (str_contains($url, 'metric=follower_demographics')) {
            return Http::response(['data' => []]);
        }

        if (str_contains($url, '/insights')) {
            return Http::response(['data' => [
                ['name' => 'reach', 'values' => [['value' => 8000]]],
                // `views` menggantikan `impressions` yang dihentikan Meta.
                ['name' => 'views', 'values' => [['value' => 15000]]],
                ['name' => 'profile_views', 'values' => [['value' => 320]]],
                ['name' => 'total_interactions', 'values' => [['value' => 230]]],
            ]]);
        }

        return Http::response([
            'username' => 'kominfokutim',
            'name' => 'Diskominfo Kutai Timur',
            'profile_picture_url' => 'https://cdn.example/pp.jpg',
            'followers_count' => 4600,
            'media_count' => 812,
        ]);
    });
}

it('menyimpan snapshot harian dan demografi dari respons Meta', function () {
    fakeInstagramGraph();

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    $snapshot = InsightSnapshot::firstOrFail();

    expect($snapshot->followers_count)->toBe(4600)
        ->and($snapshot->reach)->toBe(8000)
        ->and($snapshot->impressions)->toBe(15000)
        ->and($snapshot->profile_views)->toBe(320)
        ->and($snapshot->interactions)->toBe(230)
        // 230 interaksi / 4600 pengikut = 5,00%
        ->and((float) $snapshot->engagement_rate)->toBe(5.0);

    expect(AudienceBreakdown::where('dimension', 'age')->first()->data)
        ->toBe(['18-24' => 1200, '25-34' => 3400]);

    expect($account->fresh()->username)->toBe('kominfokutim')
        ->and($account->fresh()->last_synced_at)->not->toBeNull();
});

it('mencatat keberhasilan di log sinkronisasi', function () {
    fakeInstagramGraph();

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account, SyncLog::TRIGGER_MANUAL))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    $log = SyncLog::firstOrFail();

    expect($log->status)->toBe('success')
        ->and($log->trigger)->toBe('manual')
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('tidak menggandakan snapshot saat sinkronisasi diulang di hari yang sama', function () {
    fakeInstagramGraph();

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    foreach (range(1, 2) as $ignored) {
        (new SyncSocialAccountInsights($account))->handle(
            app(InstagramInsightService::class),
            app(FacebookInsightService::class),
        );
    }

    expect(InsightSnapshot::count())->toBe(1)
        ->and(AudienceBreakdown::where('dimension', 'age')->count())->toBe(1)
        ->and(SyncLog::count())->toBe(2);
});

it('menandai akun kedaluwarsa saat Meta menolak token', function () {
    Http::fake([
        '*' => Http::response(['error' => [
            'message' => '(#190) Access token has expired',
            'code' => 190,
            'type' => 'OAuthException',
        ]], 400),
    ]);

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    $job = new SyncSocialAccountInsights($account);
    $job->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    expect($account->fresh()->status)->toBe(SocialAccount::STATUS_EXPIRED);

    $log = SyncLog::firstOrFail();
    expect($log->status)->toBe('failed')
        ->and($log->message)->toContain('190');
});

it('tidak pernah menuliskan access token ke pesan log', function () {
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Sesuatu gagal', 'code' => 100]], 400),
    ]);

    $account = SocialAccount::factory()->create([
        'platform' => 'instagram',
        'access_token' => 'EAA-token-super-rahasia',
    ]);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    expect(SyncLog::firstOrFail()->message)->not->toContain('EAA-token-super-rahasia');
});

it('menyebar job sinkronisasi agar tidak menabrak batas panggilan Meta', function () {
    Queue::fake();

    SocialAccount::factory()->count(4)->create();
    SocialAccount::factory()->create(['status' => SocialAccount::STATUS_REVOKED]);

    (new DispatchAllAccountSyncs)->handle();

    // Akun yang dicabut izinnya tidak ikut diantrekan.
    Queue::assertPushed(SyncSocialAccountInsights::class, 4);

    // Jeda 20 detik antar job — beban tersebar, bukan menumpuk di detik yang sama.
    $delays = [];
    Queue::assertPushed(SyncSocialAccountInsights::class, function ($job) use (&$delays): bool {
        $delays[] = $job->delay;

        return true;
    });

    sort($delays);

    expect($delays)->toHaveCount(4)
        ->and((int) round($delays[0]->diffInSeconds($delays[1])))->toBe(20)
        ->and((int) round($delays[0]->diffInSeconds($delays[3])))->toBe(60);
});

it('memperpanjang token yang mendekati kedaluwarsa', function () {
    Http::fake([
        '*oauth/access_token*' => Http::response(['access_token' => 'token-baru', 'expires_in' => 5184000]),
    ]);

    $hampir = SocialAccount::factory()->expiring(3)->create(['access_token' => 'token-lama']);
    $masihLama = SocialAccount::factory()->create(['token_expires_at' => now()->addDays(50)]);

    (new RefreshExpiringTokens)->handle(app(MetaOAuthService::class), app(InstagramLoginService::class));

    expect($hampir->fresh()->access_token)->toBe('token-baru')
        ->and($hampir->fresh()->token_expires_at->isAfter(now()->addDays(50)))->toBeTrue()
        ->and($masihLama->fresh()->access_token)->not->toBe('token-baru');
});

it('menandai akun kedaluwarsa bila perpanjangan token ditolak', function () {
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Token tidak berlaku', 'code' => 190]], 400),
    ]);

    $account = SocialAccount::factory()->expiring(2)->create();

    (new RefreshExpiringTokens)->handle(app(MetaOAuthService::class), app(InstagramLoginService::class));

    expect($account->fresh()->status)->toBe(SocialAccount::STATUS_EXPIRED);
});

it('melewatkan perpanjangan bila kredensial Meta belum diisi', function () {
    // Kedua jalur harus sama-sama kosong; kalau salah satunya terisi,
    // token dari jalur itu tetap layak diperpanjang.
    config([
        'services.meta.app_id' => null,
        'services.meta.app_secret' => null,
        'services.meta.instagram_app_id' => null,
        'services.meta.instagram_app_secret' => null,
    ]);
    Http::fake();

    SocialAccount::factory()->expiring(2)->create();

    (new RefreshExpiringTokens)->handle(app(MetaOAuthService::class), app(InstagramLoginService::class));

    Http::assertNothingSent();
});

it('tetap menyimpan metrik lain saat satu metrik sudah dihentikan Meta', function () {
    // Graph API menolak SELURUH permintaan bila satu nama metrik tidak dikenal.
    // Dulu itu membuat jangkauan dan kunjungan profil ikut hilang, tersimpan
    // sebagai nol, dan sinkronisasinya tetap tercatat "berhasil".
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'metric=follower_demographics')) {
            return Http::response(['data' => []]);
        }

        if (str_contains($url, '/insights')) {
            // Nama metrik yang sudah mati ikut diminta → permintaan gabungan gagal.
            if (str_contains($url, 'total_interactions') && str_contains($url, 'reach')) {
                return Http::response(['error' => [
                    'message' => '(#100) metric[3] must be a valid insights metric',
                    'code' => 100,
                ]], 400);
            }

            if (str_contains($url, 'metric=total_interactions')) {
                return Http::response(['error' => [
                    'message' => '(#100) metric[0] must be a valid insights metric',
                    'code' => 100,
                ]], 400);
            }

            if (str_contains($url, 'metric=reach')) {
                return Http::response(['data' => [['name' => 'reach', 'values' => [['value' => 8000]]]]]);
            }

            if (str_contains($url, 'metric=views')) {
                return Http::response(['data' => [['name' => 'views', 'values' => [['value' => 15000]]]]]);
            }

            if (str_contains($url, 'metric=profile_views')) {
                return Http::response(['data' => [['name' => 'profile_views', 'values' => [['value' => 320]]]]]);
            }

            return Http::response(['data' => []]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 4600]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    $snapshot = InsightSnapshot::firstOrFail();

    expect($snapshot->reach)->toBe(8000)
        ->and($snapshot->impressions)->toBe(15000)
        ->and($snapshot->profile_views)->toBe(320)
        ->and($snapshot->interactions)->toBe(0);
});

it('menandai sinkronisasi sebagai sebagian saat ada metrik yang tidak tersedia', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/insights') && ! str_contains($url, 'follower_demographics')) {
            if (str_contains($url, 'metric=reach&') || str_contains($url, 'metric=reach%2C')) {
                return Http::response(['data' => [['name' => 'reach', 'values' => [['value' => 500]]]]]);
            }

            return Http::response(['error' => ['message' => 'metric tidak dikenal', 'code' => 100]], 400);
        }

        if (str_contains($url, 'follower_demographics')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 1000]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    $log = SyncLog::firstOrFail();

    // Nol karena Meta menolak metriknya tidak boleh terbaca sama seperti nol
    // yang sebenarnya — bedanya harus terlihat di Log Sinkronisasi.
    expect($log->status)->toBe(SyncLog::STATUS_PARTIAL)
        ->and($log->message)->toContain('impressions')
        ->and($log->message)->toContain('interactions');
});

it('mengirim timeframe yang diwajibkan Meta saat meminta demografi pengikut', function () {
    fakeInstagramGraph();

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    // Tanpa `timeframe`, Meta menolak permintaannya dan seluruh demografi
    // hilang tanpa jejak — pembilang IKK jadi nol.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'follower_demographics')
        && str_contains($request->url(), 'timeframe=this_month'));
});

it('menormalkan kunci usia-gender Instagram jadi berurutan gender lebih dulu', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'breakdown=age%2Cgender')) {
            // Instagram mengembalikan [usia, gender]; Facebook sebaliknya.
            return Http::response(['data' => [['total_value' => ['breakdowns' => [['results' => [
                ['dimension_values' => ['25-34', 'F'], 'value' => 900],
                ['dimension_values' => ['25-34', 'M'], 'value' => 700],
            ]]]]]]]);
        }

        if (str_contains($url, 'follower_demographics')) {
            return Http::response(['data' => []]);
        }

        if (str_contains($url, '/insights')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 1600]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    // Bentuk kunci harus sama dengan Facebook, kalau tidak grafik cermin
    // usia×gender tidak pernah menemukannya dan jatuh ke perkiraan.
    expect(AudienceBreakdown::where('dimension', 'age_gender')->first()->data)
        ->toBe(['F.25-34' => 900, 'M.25-34' => 700]);
});

it('meminta tiap metrik demografi Facebook secara terpisah', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'page_fans_gender_age')) {
            return Http::response(['data' => [[
                'name' => 'page_fans_gender_age',
                'values' => [['value' => ['F.25-34' => 400, 'M.25-34' => 300]]],
            ]]]);
        }

        // Dua metrik lain dianggap sudah dihentikan Meta.
        if (str_contains($url, 'page_fans_')) {
            return Http::response(['error' => ['message' => 'metrik dihentikan', 'code' => 100]], 400);
        }

        if (str_contains($url, '/insights')) {
            return Http::response(['data' => [['name' => 'page_impressions_unique', 'values' => [['value' => 90]]]]]);
        }

        return Http::response(['name' => 'Diskominfo', 'fan_count' => 700]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'facebook']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    // Satu metrik mati tidak boleh ikut menghapus demografi yang masih hidup.
    expect(AudienceBreakdown::where('dimension', 'age_gender')->first()->data)
        ->toBe(['F.25-34' => 400, 'M.25-34' => 300])
        ->and(AudienceBreakdown::where('dimension', 'age')->first()->data)->toBe(['25-34' => 700]);
});

it('meminta hari yang sudah selesai, bukan hari berjalan', function () {
    fakeInstagramGraph();

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    // Insight harian Meta baru terisi untuk hari yang sudah berakhir; meminta
    // hari berjalan hanya membuahkan data kosong.
    expect(InsightSnapshot::firstOrFail()->snapshot_date->toDateString())
        ->toBe(now()->subDay()->toDateString());
});

it('tidak menandai sebagian saat Meta membalas tanpa titik data', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'follower_demographics')) {
            return Http::response(['data' => []]);
        }

        // Bentuk nyata dari Meta untuk rentang yang belum punya data:
        // 200 dengan data kosong, bukan galat.
        if (str_contains($url, '/insights')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 4353]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    // Nol yang sebenarnya bukan metrik yang hilang — menandainya "sebagian"
    // membuat operator mengira ada yang rusak padahal tidak.
    expect(SyncLog::firstOrFail()->status)->toBe(SyncLog::STATUS_SUCCESS)
        ->and(InsightSnapshot::firstOrFail()->reach)->toBe(0);
});

it('menarik riwayat harian sekaligus saat akun baru terhubung', function () {
    // Meta membalas satu deret berisi banyak hari untuk satu panggilan.
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/insights')) {
            return Http::response(['data' => [[
                'name' => 'reach',
                'values' => [
                    ['end_time' => '2026-08-19T07:00:00+0000', 'value' => 44],
                    ['end_time' => '2026-08-20T07:00:00+0000', 'value' => 15],
                    ['end_time' => '2026-08-21T07:00:00+0000', 'value' => 16],
                ],
            ]]]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 4353]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new BackfillAccountHistory($account, 90))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    // Tanpa ini, saringan 30/90 hari kosong sampai sinkronisasi harian
    // menumpuk berminggu-minggu.
    expect(InsightSnapshot::count())->toBe(3)
        ->and(InsightSnapshot::where('snapshot_date', '2026-08-21')->value('reach'))->toBe(16)
        ->and(InsightSnapshot::where('snapshot_date', '2026-08-19')->value('reach'))->toBe(44)
        ->and(SyncLog::firstOrFail()->status)->toBe(SyncLog::STATUS_SUCCESS);
});

it('menarik metrik yang tak punya deret harian lewat permintaan per hari', function () {
    // Bentuk nyata dari Meta: `reach` dilayani sebagai deret, sedangkan
    // `total_interactions` hanya sebagai nilai total untuk satu rentang.
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'metric=reach')) {
            return Http::response(['data' => [[
                'name' => 'reach',
                'values' => [
                    ['end_time' => now()->subDays(2)->toIso8601String(), 'value' => 30],
                    ['end_time' => now()->subDay()->toIso8601String(), 'value' => 40],
                ],
            ]]]);
        }

        if (str_contains($url, 'metric=total_interactions')) {
            // Bentuk deret kosong; hanya bentuk total yang menjawab.
            return str_contains($url, 'metric_type=total_value')
                ? Http::response(['data' => [['name' => 'total_interactions', 'total_value' => ['value' => 5]]]])
                : Http::response(['data' => []]);
        }

        if (str_contains($url, '/insights')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 1_000]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new BackfillAccountHistory($account, 3))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    // Dulu interaksi seluruh riwayat tersimpan nol karena hanya bentuk deret
    // yang pernah diminta — dan engagement pun terbaca 0% terus.
    expect(InsightSnapshot::sum('interactions'))->toBeGreaterThan(0)
        ->and(InsightSnapshot::where('snapshot_date', now()->subDay()->toDateString())->value('interactions'))->toBe(5);
});

it('menahan angka negatif dari Meta di nol', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/insights')) {
            return Http::response(['data' => [
                ['name' => 'reach', 'values' => [['value' => 10]]],
                // Suka yang dibatalkan bisa membuat nilainya negatif.
                ['name' => 'total_interactions', 'values' => [['value' => -3]]],
            ]]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 1_000]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new SyncSocialAccountInsights($account))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    // Kolomnya unsigned — angka negatif akan menggagalkan penyimpanan.
    expect(InsightSnapshot::firstOrFail()->interactions)->toBe(0);
});

it('menyusun kurva pengikut historis dari pertambahan harian', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'metric=follower_count')) {
            return Http::response(['data' => [[
                'name' => 'follower_count',
                'values' => [
                    ['end_time' => now()->subDays(3)->toIso8601String(), 'value' => 5],
                    ['end_time' => now()->subDays(2)->toIso8601String(), 'value' => 10],
                    ['end_time' => now()->subDay()->toIso8601String(), 'value' => 20],
                ],
            ]]]);
        }

        if (str_contains($url, 'metric=reach')) {
            return Http::response(['data' => [[
                'name' => 'reach',
                'values' => [
                    ['end_time' => now()->subDays(3)->toIso8601String(), 'value' => 1],
                    ['end_time' => now()->subDays(2)->toIso8601String(), 'value' => 1],
                    ['end_time' => now()->subDay()->toIso8601String(), 'value' => 1],
                ],
            ]]]);
        }

        if (str_contains($url, '/insights')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 1_000]);
    });

    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    (new BackfillAccountHistory($account, 4))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    $kurva = InsightSnapshot::orderBy('snapshot_date')->pluck('followers_count', 'snapshot_date');

    /*
     | Total hari ini 1.000; mundur dikurangi pertambahan tiap hari.
     | Dulu seluruh hari memakai 1.000, sehingga grafik trennya datar sempurna
     | dan terbaca seolah tidak seorang pun pernah mengikuti selama 90 hari.
    */
    expect($kurva[now()->subDay()->toDateString()])->toBe(1_000)
        ->and($kurva[now()->subDays(2)->toDateString()])->toBe(980)
        ->and($kurva[now()->subDays(3)->toDateString()])->toBe(970)
        // Kurvanya harus menanjak, bukan mendatar.
        ->and($kurva->values()->unique()->count())->toBeGreaterThan(1);
});

it('tidak menghapus interaksi lama saat riwayat panjang ditarik ulang', function () {
    $account = SocialAccount::factory()->create(['platform' => 'instagram']);

    // Angka yang sudah dikumpulkan sinkronisasi harian berbulan-bulan lalu.
    $lama = InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->subDays(200)->toDateString(),
        'followers_count' => 4_000,
        'reach' => 50,
        'interactions' => 77,
        'profile_views' => 12,
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        // Jangkauan tersedia sebagai deret untuk seluruh rentang…
        if (str_contains($url, 'metric=reach')) {
            return Http::response(['data' => [[
                'name' => 'reach',
                'values' => [['end_time' => now()->subDays(200)->toIso8601String(), 'value' => 60]],
            ]]]);
        }

        // …sedangkan interaksi & kunjungan profil tidak, dan penarikan per hari
        // dibatasi hanya beberapa puluh hari terakhir.
        if (str_contains($url, '/insights')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['username' => 'kominfokutim', 'followers_count' => 4_100]);
    });

    (new BackfillAccountHistory($account, 365))->handle(
        app(InstagramInsightService::class),
        app(FacebookInsightService::class),
    );

    $segar = $lama->fresh();

    // Menulis nol untuk kolom yang tidak ikut ditarik akan memusnahkan riwayat
    // interaksi yang dikumpulkan pelan-pelan selama berbulan-bulan.
    expect($segar->interactions)->toBe(77)
        ->and($segar->profile_views)->toBe(12)
        // Yang memang ditarik tetap diperbarui.
        ->and($segar->reach)->toBe(60);
});

it('menarik riwayat setahun penuh secara bawaan', function () {
    expect((new BackfillAccountHistory(SocialAccount::factory()->create()))->days)
        ->toBe(365);
});

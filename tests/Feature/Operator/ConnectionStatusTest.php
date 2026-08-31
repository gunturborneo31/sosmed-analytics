<?php

use App\Jobs\SyncSocialAccountInsights;
use App\Livewire\Operator\ConnectionStatus;
use App\Livewire\Operator\OwnInsight;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Support\SocialPlatform;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->unit = OrganizationalUnit::factory()->kecamatan('Sangatta Utara')->create();
    $this->operator = operatorUser($this->unit);
});

it('menampilkan prasyarat Meta sebelum operator mencoba menghubungkan', function () {
    Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->assertOk()
        ->assertSee('Business')
        ->assertSee('Facebook Page')
        ->assertSee('Belum ada akun terhubung');
});

it('memperingatkan bila kredensial Meta belum diisi', function () {
    config(['services.meta.app_id' => null, 'services.meta.app_secret' => null]);

    Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->assertSee('Kredensial Meta belum dikonfigurasi');
});

it('hanya menampilkan akun milik instansi operator', function () {
    $milikSendiri = SocialAccount::factory()->create([
        'organizational_unit_id' => $this->unit->id,
        'display_name' => 'Kecamatan Sangatta Utara',
    ]);

    $lain = OrganizationalUnit::factory()->create(['name' => 'Dinas Kesehatan']);
    SocialAccount::factory()->create([
        'organizational_unit_id' => $lain->id,
        'display_name' => 'Dinas Kesehatan Kutim',
    ]);

    $component = Livewire::actingAs($this->operator)->test(ConnectionStatus::class);

    expect($component->get('accounts')->pluck('id')->all())->toBe([$milikSendiri->id]);

    $component->assertSee('Kecamatan Sangatta Utara')
        ->assertDontSee('Dinas Kesehatan Kutim');
});

it('mengantrekan sinkronisasi manual untuk akun sendiri', function () {
    Queue::fake();

    $account = SocialAccount::factory()->create(['organizational_unit_id' => $this->unit->id]);

    Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->call('syncNow', $account->id);

    Queue::assertPushed(SyncSocialAccountInsights::class, fn ($job): bool => $job->account->is($account)
        && $job->trigger === 'manual');
});

it('menolak sinkronisasi manual atas akun OPD lain', function () {
    Queue::fake();

    $lain = OrganizationalUnit::factory()->create();
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $lain->id]);

    // findOrFail pada query yang sudah tersaring OPD: akun milik instansi lain
    // tidak pernah ada di dalam jangkauan operator ini.
    expect(fn () => Livewire::actingAs($this->operator)
        ->test(ConnectionStatus::class)
        ->call('syncNow', $account->id))
        ->toThrow(ModelNotFoundException::class);

    Queue::assertNothingPushed();
});

it('mengarahkan operator menghubungkan akun sebelum ada insight', function () {
    Livewire::actingAs($this->operator)
        ->test(OwnInsight::class)
        ->assertOk()
        ->assertSee('Belum ada akun terhubung');
});

it('menampilkan insight setelah ada data', function () {
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $this->unit->id]);

    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'followers_count' => 3400,
        'reach' => 7000,
        'engagement_rate' => 2.5,
    ]);

    $component = Livewire::actingAs($this->operator)->test(OwnInsight::class)->assertOk();

    expect($component->get('summary')['followers'])->toBe(3400)
        ->and($component->get('summary')['reach'])->toBe(7000);
});

it('mengunci cakupan insight operator ke instansinya sendiri', function () {
    $lain = OrganizationalUnit::factory()->create();
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $lain->id]);

    InsightSnapshot::factory()->create([
        'social_account_id' => $account->id,
        'snapshot_date' => now()->toDateString(),
        'followers_count' => 99999,
    ]);

    $component = Livewire::actingAs($this->operator)->test(OwnInsight::class);

    expect($component->get('summary')['followers'])->toBe(0);
});

it('menandai tiap akun dengan lambang dan warna platformnya', function () {
    SocialAccount::factory()->create([
        'organizational_unit_id' => $this->unit->id,
        'platform' => SocialAccount::PLATFORM_INSTAGRAM,
        'username' => 'kecamatansangattautara',
    ]);

    SocialAccount::factory()->facebook()->create([
        'organizational_unit_id' => $this->unit->id,
        'username' => 'sangattautara.fb',
    ]);

    $component = Livewire::actingAs($this->operator)->test(ConnectionStatus::class);

    $component->assertSee('Instagram')
        ->assertSee('@kecamatansangattautara')
        ->assertSee('Facebook')
        ->assertSee('@sangattautara.fb')
        // Warna merek, bukan token aplikasi — Instagram gradien, Facebook biru.
        ->assertSeeHtml('linear-gradient(45deg,#F09433')
        ->assertSeeHtml('background-color: #1877F2');
});

it('memakai warna merek yang berbeda untuk teks username tiap platform', function () {
    expect(SocialPlatform::textClass('instagram'))
        ->toBe('text-[#C13584] dark:text-[#E981B4]')
        ->and(SocialPlatform::textClass('facebook'))
        ->toBe('text-[#1877F2] dark:text-[#6BAAF9]')
        // Platform tak dikenal jatuh ke token aplikasi, bukan warna asal-asalan.
        ->and(SocialPlatform::textClass('tiktok'))->toBe('text-ink-muted')
        ->and(SocialPlatform::label('tiktok'))->toBe('Tiktok');
});

it('menawarkan tombol hubungkan terpisah per platform saat belum ada akun', function () {
    config([
        'services.meta.app_id' => '123',
        'services.meta.app_secret' => 'rahasia',
        'services.meta.instagram_app_id' => '456',
        'services.meta.instagram_app_secret' => 'rahasia-ig',
    ]);

    $html = Livewire::actingAs($this->operator)->test(ConnectionStatus::class)->html();

    // Dulu kartu ini hanya punya satu tombol yang selalu menuju jalur Facebook,
    // sehingga operator yang ingin Instagram tidak punya jalan dari sini.
    expect($html)->toContain('Hubungkan Instagram')
        ->and($html)->toContain('Hubungkan Facebook')
        ->and($html)->toContain(route('oauth.instagram.redirect'))
        // Warna merek masing-masing kanal ikut terbawa ke tombolnya.
        ->and($html)->toContain('#1877F2')
        ->and($html)->toContain('linear-gradient(45deg,#F09433');
});

it('mematikan tombol kanal yang kredensialnya belum diisi', function () {
    config([
        'services.meta.app_id' => '123',
        'services.meta.app_secret' => 'rahasia',
        // Jalur Instagram sengaja dikosongkan.
        'services.meta.instagram_app_id' => null,
        'services.meta.instagram_app_secret' => null,
    ]);

    $html = Livewire::actingAs($this->operator)->test(ConnectionStatus::class)->html();

    // Tombolnya tetap terlihat supaya operator tahu kanalnya ada, tapi tidak
    // bisa diklik menuju Meta dan sebabnya dijelaskan lewat title.
    expect($html)->toContain('Kredensial Instagram belum diisi di .env')
        ->and($html)->toContain('aria-disabled="true"');
});

it('mempertahankan tombol hubungkan hanya untuk yang berwenang', function () {
    // Izin datang dari peran, jadi peranlah yang dicabut — bukan izin langsung.
    $tamu = operatorUser($this->unit);
    $tamu->syncRoles([]);

    $html = Livewire::actingAs($tamu)->test(ConnectionStatus::class)->html();

    expect($html)->not->toContain('Hubungkan Instagram')
        ->and($html)->not->toContain('Hubungkan Facebook');
});

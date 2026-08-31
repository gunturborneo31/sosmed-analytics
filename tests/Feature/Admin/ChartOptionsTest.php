<?php

use App\Livewire\Admin\AgeSpectrum;
use App\Livewire\Admin\CountyOverview;
use App\Livewire\Admin\UnitComparison;
use App\Livewire\Admin\UnitDetail;
use App\Livewire\Operator\OwnInsight;
use App\Models\InsightSnapshot;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use Livewire\Livewire;

/**
 * ApexCharts memanggil `formatter` sebagai fungsi. Nilai null lolos dari PHP,
 * berubah jadi `{"formatter": null}` di JSON, lalu membuat grafik mati tanpa
 * pesan apa pun — kartunya hanya tampil kosong. Test ini menjaga agar kunci
 * callback tidak pernah dikirim bernilai null.
 */
function assertNoNullCallbacks(array $options, string $path = ''): void
{
    $callbackKeys = ['formatter', 'custom', 'dataPointSelection', 'click', 'events', 'labels.formatter'];

    foreach ($options as $key => $value) {
        $here = $path === '' ? (string) $key : "{$path}.{$key}";

        if (in_array((string) $key, $callbackKeys, true)) {
            expect($value)->not->toBeNull("opsi grafik [{$here}] bernilai null — ApexCharts akan gagal diam-diam");
        }

        if (is_array($value)) {
            assertNoNullCallbacks($value, $here);
        }
    }
}

function unitDenganTren(): OrganizationalUnit
{
    $unit = OrganizationalUnit::factory()->create();
    $account = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    foreach (range(0, 9) as $back) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $account->id,
            'snapshot_date' => now()->subDays($back)->toDateString(),
            'followers_count' => 1000 + $back * 25,
            'reach' => 3000 + $back * 40,
        ]);
    }

    return $unit;
}

it('tidak pernah mengirim callback bernilai null ke ApexCharts', function () {
    $unit = unitDenganTren();
    $admin = adminUser($unit);

    $charts = [
        'ringkasan.tren' => Livewire::actingAs($admin)->test(CountyOverview::class)->get('trendChart'),
        'ringkasan.usia' => Livewire::actingAs($admin)->test(CountyOverview::class)->get('ageChart'),
        'spektrum' => Livewire::actingAs($admin)->test(AgeSpectrum::class)->get('chart'),
        'detail.tren' => Livewire::actingAs($admin)->test(UnitDetail::class, ['unit' => $unit])->get('trendChart'),
        'banding.tren' => Livewire::actingAs($admin)->test(UnitComparison::class)->set('selected', [$unit->id])->get('trendChart'),
        'operator.tren' => Livewire::actingAs(operatorUser($unit))->test(OwnInsight::class)->get('trendChart'),
    ];

    foreach ($charts as $name => $options) {
        assertNoNullCallbacks($options, $name);
    }
});

it('mengisi grafik tren dengan satu titik per hari dalam periode', function () {
    $unit = unitDenganTren();

    $chart = Livewire::actingAs(adminUser($unit))
        ->test(CountyOverview::class)
        ->set('period', '7')
        ->get('trendChart');

    expect($chart['series'][0]['data'])->toHaveCount(7)
        ->and($chart['xaxis']['categories'])->toHaveCount(7)
        ->and(array_filter($chart['series'][0]['data']))->not->toBeEmpty();
});

it('mengirim sumbu pengikut yang merapat ke datanya, bukan mulai dari nol', function () {
    $unit = OrganizationalUnit::factory()->create();
    $akun = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    foreach ([4_327, 4_340, 4_353] as $i => $pengikut) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $akun->id,
            'snapshot_date' => now()->subDays(3 - $i)->toDateString(),
            'followers_count' => $pengikut,
            'reach' => 20,
        ]);
    }

    $c = new UnitDetail;
    $c->unit = $unit;
    $sumbu = $c->trendChart()['yaxis'];

    /*
     | Bentuknya WAJIB array dua sumbu (pengikut kiri, jangkauan kanan).
     | Sisi JavaScript memasang formatter angka ke tiap sumbu; kalau bentuknya
     | berubah jadi objek tunggal, penanganannya di sana ikut salah dan seluruh
     | setelan min/max hilang — sumbunya balik menghitung dari nol dan kenaikan
     | 26 pengikut di atas 4.353 tampak sebagai garis lurus.
    */
    expect(array_is_list($sumbu))->toBeTrue()
        ->and($sumbu)->toHaveCount(2)
        ->and($sumbu[0]['min'])->toBeLessThan(4_327)
        ->and($sumbu[0]['max'])->toBeGreaterThan(4_353)
        // Sumbu jangkauan tetap dari nol: itu hitungan harian, bukan saldo berjalan.
        ->and($sumbu[1]['min'])->toBe(0);
});

it('menyisakan rentang sumbu meski seluruh pengikutnya sama', function () {
    $unit = OrganizationalUnit::factory()->create();
    $akun = SocialAccount::factory()->create(['organizational_unit_id' => $unit->id]);

    foreach ([0, 1, 2] as $mundur) {
        InsightSnapshot::factory()->create([
            'social_account_id' => $akun->id,
            'snapshot_date' => now()->subDays($mundur)->toDateString(),
            'followers_count' => 4_353,
        ]);
    }

    $sumbu = tap(new UnitDetail, fn ($c) => $c->unit = $unit)->trendChart()['yaxis'];

    // Rentang nol membuat ApexCharts menggambar sumbu tanpa tinggi sama sekali.
    expect($sumbu[0]['max'])->toBeGreaterThan($sumbu[0]['min']);
});

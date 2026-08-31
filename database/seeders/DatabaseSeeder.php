<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\MediaSource;
use App\Models\SearchTopic;
use App\Models\ScrapingSchedule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            OrganizationalUnitSeeder::class,
        ]);

        $kominfo = OrganizationalUnit::where('slug', 'dinas-komunikasi-dan-informatika')->firstOrFail();

        $this->user('Super Admin', 'super@kutimkab.go.id', 'super-admin', $kominfo);
        $this->user('Admin Diskominfo', 'admin@kutimkab.go.id', 'admin-kominfo', $kominfo);

        $sangattaUtara = OrganizationalUnit::where('slug', 'kecamatan-sangatta-utara')->firstOrFail();
        $this->user('Operator Sangatta Utara', 'operator@kutimkab.go.id', 'operator-opd', $sangattaUtara);

        foreach ([
            ['Suara Kutim', 'https://suarakutim.com/', 'https://suarakutim.com/feed/'],
            ['Kutim Daily', 'https://kutimdaily.com/', 'https://kutimdaily.com/feed/'],
            ['Cerita Sangatta', 'https://ceritasangattaku.com/', 'https://ceritasangattaku.com/feed/'],
            ['Kutim Post', 'https://kutimpost.com/', 'https://kutimpost.com/feed/'],
            ['Up News', 'https://www.upnews.id/', 'https://www.upnews.id/feed/'],
        ] as [$name, $baseUrl, $feedUrl]) {
            MediaSource::updateOrCreate(['base_url' => $baseUrl], ['name' => $name, 'feed_url' => $feedUrl, 'is_active' => true]);
        }
        SearchTopic::updateOrCreate(['keyword' => 'bupati'], ['time_filter_type' => 'all', 'start_date' => null, 'end_date' => null, 'is_active' => true]);
        ScrapingSchedule::firstOrCreate(['id' => 1], ['frequency_minutes' => 60, 'is_active' => true]);
    }

    private function user(string $name, string $email, string $role, OrganizationalUnit $unit): void
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'organizational_unit_id' => $unit->id,
            ],
        );

        $user->syncRoles([$role]);
    }
}

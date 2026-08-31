<?php

use App\Jobs\BuildDailyAggregates;
use App\Jobs\DispatchAllAccountSyncs;
use App\Jobs\RefreshExpiringTokens;
use Illuminate\Support\Facades\Schedule;
use App\Models\ScrapingSchedule;

// Sinkronisasi insight — 2x sehari di jam sepi (§9.5).
Schedule::job(new DispatchAllAccountSyncs)->twiceDailyAt(3, 15, 0);

// Perpanjang token yang kedaluwarsa dalam 7 hari.
Schedule::job(new RefreshExpiringTokens)->dailyAt('02:00');

// Hangatkan agregat untuk halaman admin.
Schedule::job(new BuildDailyAggregates)->dailyAt('04:00');

Schedule::command('news:scrape')->everyMinute()->when(function (): bool {
	$schedule = ScrapingSchedule::query()->where('is_active', true)->first();
	$frequency = $schedule?->frequency_minutes ?? 60;

	return now()->timestamp % (max(1, $frequency) * 60) < 60;
});

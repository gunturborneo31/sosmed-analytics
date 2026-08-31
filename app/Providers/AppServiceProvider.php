<?php

namespace App\Providers;

use App\Services\Meta\FacebookInsightService;
use App\Services\Meta\InstagramInsightService;
use App\Services\Meta\MetaGraphClient;
use App\Services\Meta\MetaOAuthService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Konstruktor service Meta memakai parameter skalar, jadi tidak bisa di-autowire.
        $this->app->singleton(MetaGraphClient::class, fn (): MetaGraphClient => MetaGraphClient::make());

        foreach ([MetaOAuthService::class, InstagramInsightService::class, FacebookInsightService::class] as $service) {
            $this->app->singleton($service, fn ($app): object => new $service($app->make(MetaGraphClient::class)));
        }
    }

    public function boot(): void
    {
        // Tanggal & label periode ditampilkan dalam bahasa Indonesia.
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));

        // Meta menolak redirect URI non-HTTPS di produksi (§12).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::unguard(false);
    }
}

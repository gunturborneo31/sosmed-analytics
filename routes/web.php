<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Meta\InstagramOAuthController;
use App\Http\Controllers\Meta\MetaOAuthController;
use App\Livewire\Admin\CountyOverview;
use App\Livewire\Admin\DemographicsPanel;
use App\Livewire\Admin\ReportBuilder;
use App\Livewire\Admin\SyncLogTable;
use App\Livewire\Admin\UnitComparison;
use App\Livewire\Admin\UnitDetail;
use App\Livewire\Admin\UnitDirectory;
use App\Livewire\Admin\UserDirectory;
use App\Livewire\Admin\NewsAggregator;
use App\Livewire\Operator\ConnectionStatus;
use App\Livewire\Operator\OwnInsight;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
|--------------------------------------------------------------------------
| Tamu — pendaftaran mandiri sengaja tidak disediakan (§6)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('/lupa-sandi', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-sandi', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/atur-sandi/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/atur-sandi', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Operator OPD — sengaja sederhana (§1)
    |----------------------------------------------------------------------
    */
    Route::middleware('can:view-own-insights')->group(function () {
        Route::get('/akun', ConnectionStatus::class)->name('operator.accounts');
        Route::get('/insight', OwnInsight::class)->name('operator.insight');
    });

    /*
    |----------------------------------------------------------------------
    | Admin Diskominfo — seluruh kedalaman analitik ada di sini
    |----------------------------------------------------------------------
    */
    Route::middleware('can:view-all-insights')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', CountyOverview::class)->name('overview');
        Route::get('/perangkat-daerah', UnitDirectory::class)->name('units');
        Route::get('/perangkat-daerah/{unit}', UnitDetail::class)->name('units.show');
        Route::get('/demografi', DemographicsPanel::class)->name('demographics');
        Route::get('/perbandingan', UnitComparison::class)->name('comparison');
        Route::get('/rekap', ReportBuilder::class)->name('recap');
        Route::get('/log-sinkronisasi', SyncLogTable::class)->name('sync-logs');
        Route::get('/agregator-berita', NewsAggregator::class)->name('news');
    });

    Route::get('/admin/pengguna', UserDirectory::class)
        ->middleware('can:manage-users')
        ->name('admin.users');

    /*
    |----------------------------------------------------------------------
    | OAuth Meta (§9.3)
    |----------------------------------------------------------------------
    */
    Route::middleware(['can:connect-social-account', 'throttle:12,1'])
        ->prefix('oauth/meta')->name('oauth.meta.')->group(function () {
            Route::get('/redirect', [MetaOAuthController::class, 'redirect'])->name('redirect');
            Route::get('/callback', [MetaOAuthController::class, 'callback'])->name('callback');
            Route::delete('/{account}', [MetaOAuthController::class, 'destroy'])->name('disconnect');
        });

    /*
    |----------------------------------------------------------------------
    | Instagram Business Login — jalur langsung, tanpa Facebook Page
    |----------------------------------------------------------------------
    */
    Route::middleware(['can:connect-social-account', 'throttle:12,1'])
        ->prefix('oauth/instagram')->name('oauth.instagram.')->group(function () {
            Route::get('/redirect', [InstagramOAuthController::class, 'redirect'])->name('redirect');
            Route::get('/callback', [InstagramOAuthController::class, 'callback'])->name('callback');
        });
});

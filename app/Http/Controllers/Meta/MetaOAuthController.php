<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Jobs\BackfillAccountHistory;
use App\Jobs\SyncSocialAccountInsights;
use App\Models\SocialAccount;
use App\Services\Meta\MetaGraphException;
use App\Services\Meta\MetaOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaOAuthController extends Controller
{
    public function __construct(private readonly MetaOAuthService $oauth) {}

    /**
     * Langkah 1–2 (§9.3): kirim user ke dialog izin Meta dengan state anti-CSRF.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return redirect()->route('operator.accounts')->with(
                'error',
                'Kredensial Meta belum diisi. Lengkapi META_APP_ID dan META_APP_SECRET di .env lebih dulu.',
            );
        }

        if ($request->user()->organizational_unit_id === null) {
            return redirect()->route('operator.accounts')->with(
                'error', 'Akunmu belum ditautkan ke perangkat daerah. Hubungi admin Diskominfo.',
            );
        }

        // Platform yang dipilih operator di dialog. Nilai di luar daftar
        // diabaikan — kembali ke perilaku lama yang meminta seluruh izin.
        $platform = in_array($request->query('platform'), [
            SocialAccount::PLATFORM_INSTAGRAM,
            SocialAccount::PLATFORM_FACEBOOK,
        ], true) ? $request->query('platform') : null;

        $state = Str::random(40);

        $request->session()->put('meta_oauth_state', $state);
        $request->session()->put('meta_oauth_unit', $request->user()->organizational_unit_id);
        $request->session()->put('meta_oauth_platform', $platform);

        return redirect()->away($this->oauth->authorizationUrl($state, $platform));
    }

    /**
     * Langkah 4–11 (§9.3).
     */
    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('meta_oauth_state');
        $unitId = $request->session()->pull('meta_oauth_unit');
        $platform = $request->session()->pull('meta_oauth_platform');

        // §12: state wajib cocok, kalau tidak permintaan ini bukan berasal dari kita.
        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('operator.accounts')
                ->with('error', 'Sesi otorisasi tidak cocok. Silakan ulangi dari awal.');
        }

        if ($request->has('error')) {
            return redirect()->route('operator.accounts')->with(
                'error',
                'Izin dibatalkan di halaman Meta'
                    .($request->query('error_description') ? ': '.$request->query('error_description') : '.'),
            );
        }

        if (! $request->filled('code')) {
            return redirect()->route('operator.accounts')
                ->with('error', 'Meta tidak mengirimkan kode otorisasi.');
        }

        try {
            $token = $this->oauth->exchangeCodeForLongLivedToken($request->string('code')->toString());
            $accounts = $this->oauth->managedAccounts($token['token']);
        } catch (MetaGraphException $e) {
            // Token tidak pernah ikut tercatat di log (§12).
            Log::warning('Otorisasi Meta gagal', ['unit' => $unitId, 'pesan' => $e->getMessage()]);

            return redirect()->route('operator.accounts')
                ->with('error', 'Gagal menghubungkan Meta. '.$e->pesanUntukOperator());
        }

        // Operator memilih satu platform di dialog; yang lain tidak ikut disimpan
        // meski Meta mengembalikannya dalam respons yang sama.
        if ($platform) {
            $accounts = $accounts->where('platform', $platform)->values();
        }

        if ($accounts->isEmpty()) {
            return redirect()->route('operator.accounts')->with(
                'error',
                $platform === SocialAccount::PLATFORM_INSTAGRAM
                    ? 'Tidak ada akun Instagram yang bisa dihubungkan. Pastikan akunnya bertipe '
                        .'Business atau Creator, lalu tautkan ke Facebook Page instansi — akun '
                        .'personal tidak menyediakan data insight.'
                    : 'Tidak ada Facebook Page yang kamu kelola. Buat Page untuk instansi lebih '
                        .'dulu, atau minta admin Page menambahkanmu sebagai admin.',
            );
        }

        $saved = 0;

        foreach ($accounts as $account) {
            $existing = SocialAccount::where('platform', $account['platform'])
                ->where('platform_account_id', $account['platform_account_id'])
                ->first();

            // Satu akun medsos hanya boleh dimiliki satu OPD.
            if ($existing && $existing->organizational_unit_id !== $unitId) {
                Log::warning('Akun medsos sudah terdaftar di OPD lain', [
                    'platform' => $account['platform'],
                    'unit_pemilik' => $existing->organizational_unit_id,
                ]);

                continue;
            }

            $record = SocialAccount::updateOrCreate(
                [
                    'platform' => $account['platform'],
                    'platform_account_id' => $account['platform_account_id'],
                ],
                [
                    'organizational_unit_id' => $unitId,
                    'connected_by' => $request->user()->id,
                    'username' => $account['username'],
                    'display_name' => $account['display_name'],
                    'avatar_url' => $account['avatar_url'],
                    'access_token' => $account['access_token'],
                    'token_expires_at' => $token['expires_at'],
                    'status' => SocialAccount::STATUS_CONNECTED,
                ],
            );

            SyncSocialAccountInsights::dispatch($record);

            // Riwayat ditarik sekaligus supaya saringan 7/30/90 hari langsung
            // ada isinya, bukan menunggu sinkronisasi harian menumpuk sebulan.
            BackfillAccountHistory::dispatch($record);
            $saved++;
        }

        if ($saved === 0) {
            return redirect()->route('operator.accounts')->with(
                'error', 'Akun tersebut sudah terdaftar atas nama perangkat daerah lain.',
            );
        }

        return redirect()->route('operator.accounts')->with(
            'success', "{$saved} akun berhasil dihubungkan. Sinkronisasi pertama sedang berjalan.",
        );
    }

    public function destroy(Request $request, SocialAccount $account): RedirectResponse
    {
        abort_unless(
            $request->user()->seesAllUnits()
                || $account->organizational_unit_id === $request->user()->organizational_unit_id,
            403,
        );

        $account->delete();

        return redirect()->route('operator.accounts')
            ->with('success', 'Akun diputus. Data insight yang sudah tersimpan ikut terhapus.');
    }
}

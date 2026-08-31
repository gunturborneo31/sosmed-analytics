<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Jobs\BackfillAccountHistory;
use App\Jobs\SyncSocialAccountInsights;
use App\Models\SocialAccount;
use App\Services\Meta\InstagramLoginService;
use App\Services\Meta\MetaGraphException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Instagram Business Login — operator masuk dengan akun Instagram-nya sendiri,
 * tanpa perlu Facebook Page. Alur Facebook tetap tersedia di MetaOAuthController.
 */
class InstagramOAuthController extends Controller
{
    public function __construct(private readonly InstagramLoginService $instagram) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->instagram->isConfigured()) {
            return redirect()->route('operator.accounts')->with(
                'error',
                'Instagram Login belum dikonfigurasi. Isi INSTAGRAM_APP_ID dan INSTAGRAM_APP_SECRET '
                    .'di .env — keduanya berbeda dari kredensial Facebook.',
            );
        }

        if ($request->user()->organizational_unit_id === null) {
            return redirect()->route('operator.accounts')->with(
                'error', 'Akunmu belum ditautkan ke perangkat daerah. Hubungi admin Diskominfo.',
            );
        }

        $state = Str::random(40);

        $request->session()->put('ig_oauth_state', $state);
        $request->session()->put('ig_oauth_unit', $request->user()->organizational_unit_id);

        return redirect()->away($this->instagram->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('ig_oauth_state');
        $unitId = $request->session()->pull('ig_oauth_unit');

        // §12: state wajib cocok, kalau tidak permintaan ini bukan berasal dari kita.
        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('operator.accounts')
                ->with('error', 'Sesi otorisasi tidak cocok. Silakan ulangi dari awal.');
        }

        if ($request->has('error')) {
            return redirect()->route('operator.accounts')->with(
                'error',
                'Izin dibatalkan di halaman Instagram'
                    .($request->query('error_description') ? ': '.$request->query('error_description') : '.'),
            );
        }

        if (! $request->filled('code')) {
            return redirect()->route('operator.accounts')
                ->with('error', 'Instagram tidak mengirimkan kode otorisasi.');
        }

        try {
            $token = $this->instagram->exchangeCodeForLongLivedToken($request->string('code')->toString());
            $profil = $this->instagram->profile($token['token']);
        } catch (MetaGraphException $e) {
            // Token tidak pernah ikut tercatat di log (§12).
            Log::warning('Otorisasi Instagram gagal', ['unit' => $unitId, 'pesan' => $e->getMessage()]);

            return redirect()->route('operator.accounts')
                ->with('error', 'Gagal menghubungkan Instagram. '.$e->pesanUntukOperator());
        }

        $igId = (string) ($profil['user_id'] ?? $token['user_id']);

        if ($igId === '') {
            return redirect()->route('operator.accounts')->with(
                'error',
                'Instagram tidak mengembalikan ID akun. Pastikan akunnya bertipe Business atau Creator — '
                    .'akun personal tidak menyediakan data insight.',
            );
        }

        $existing = SocialAccount::where('platform', SocialAccount::PLATFORM_INSTAGRAM)
            ->where('platform_account_id', $igId)
            ->first();

        // Satu akun medsos hanya boleh dimiliki satu OPD.
        if ($existing && $existing->organizational_unit_id !== $unitId) {
            Log::warning('Akun Instagram sudah terdaftar di OPD lain', [
                'unit_pemilik' => $existing->organizational_unit_id,
            ]);

            return redirect()->route('operator.accounts')->with(
                'error', 'Akun Instagram itu sudah terdaftar atas nama perangkat daerah lain.',
            );
        }

        $account = SocialAccount::updateOrCreate(
            [
                'platform' => SocialAccount::PLATFORM_INSTAGRAM,
                'platform_account_id' => $igId,
            ],
            [
                'organizational_unit_id' => $unitId,
                'connected_by' => $request->user()->id,
                'auth_source' => SocialAccount::AUTH_INSTAGRAM,
                'username' => $profil['username'] ?? null,
                'display_name' => $profil['name'] ?? ($profil['username'] ?? null),
                'avatar_url' => $profil['profile_picture_url'] ?? null,
                'access_token' => $token['token'],
                'token_expires_at' => $token['expires_at'],
                'status' => SocialAccount::STATUS_CONNECTED,
            ],
        );

        SyncSocialAccountInsights::dispatch($account);

        // Riwayat ditarik sekaligus supaya saringan 7/30/90 hari langsung
        // ada isinya, bukan menunggu sinkronisasi harian menumpuk sebulan.
        BackfillAccountHistory::dispatch($account);

        return redirect()->route('operator.accounts')->with(
            'success',
            'Akun Instagram @'.($profil['username'] ?? $igId).' berhasil dihubungkan. '
                .'Sinkronisasi pertama sedang berjalan.',
        );
    }
}

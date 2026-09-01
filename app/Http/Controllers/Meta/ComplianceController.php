<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Meta\SignedRequestVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Endpoint wajib Meta App Review (§14): dipanggil otomatis oleh server Meta,
 * bukan oleh pengguna — saat operator mencabut izin dari pengaturan
 * Facebook/Instagram, atau mengirim permintaan hapus data lewat kanal Meta
 * (bukan lewat aplikasi ini).
 *
 * Keduanya diverifikasi lewat `signed_request` (§12): ditandatangani App
 * Secret, bukan access token, jadi tidak bisa dipalsukan pihak luar. Rute ini
 * sengaja berada di luar middleware `auth` — Meta memanggilnya tanpa sesi
 * login — dan dikecualikan dari verifikasi CSRF di `bootstrap/app.php`.
 */
class ComplianceController extends Controller
{
    public function instagramDeauthorize(Request $request): Response
    {
        return $this->deauthorize(
            $request,
            SocialAccount::PLATFORM_INSTAGRAM,
            (string) config('services.meta.instagram_app_secret'),
        );
    }

    public function facebookDeauthorize(Request $request): Response
    {
        return $this->deauthorize(
            $request,
            SocialAccount::PLATFORM_FACEBOOK,
            (string) config('services.meta.app_secret'),
        );
    }

    public function instagramDataDeletion(Request $request): JsonResponse
    {
        return $this->dataDeletion(
            $request,
            SocialAccount::PLATFORM_INSTAGRAM,
            (string) config('services.meta.instagram_app_secret'),
        );
    }

    public function facebookDataDeletion(Request $request): JsonResponse
    {
        return $this->dataDeletion(
            $request,
            SocialAccount::PLATFORM_FACEBOOK,
            (string) config('services.meta.app_secret'),
        );
    }

    /**
     * Ditandai `revoked`, bukan langsung dihapus — akun yang izinnya dicabut
     * tetap perlu tampak di halaman Akun, supaya operator tahu harus
     * menghubungkan ulang, bukan tiba-tiba hilang tanpa keterangan.
     */
    private function deauthorize(Request $request, string $platform, string $secret): Response
    {
        $data = SignedRequestVerifier::decode((string) $request->input('signed_request'), $secret);

        if ($data === null) {
            Log::warning('Signed request deauthorize Meta gagal diverifikasi', ['platform' => $platform]);

            return response('', 400);
        }

        $userId = (string) ($data['user_id'] ?? '');

        // Instagram Business Login: `user_id` di signed_request ADALAH ID
        // akun bisnis itu sendiri — sama dengan `platform_account_id` yang
        // sudah tersimpan — jadi bisa langsung dicocokkan. Facebook Login
        // mengirim ID pengguna pribadi yang menautkan Page, bukan ID Page,
        // dan ID itu tidak tersimpan di tabel ini; permintaannya tetap
        // tercatat di log untuk audit, tidak diproses otomatis.
        if ($platform === SocialAccount::PLATFORM_INSTAGRAM && $userId !== '') {
            SocialAccount::where('platform', $platform)
                ->where('platform_account_id', $userId)
                ->update(['status' => SocialAccount::STATUS_REVOKED]);
        }

        Log::info('Izin Meta dicabut lewat webhook deauthorize', ['platform' => $platform, 'user_id' => $userId]);

        return response('', 200);
    }

    private function dataDeletion(Request $request, string $platform, string $secret): JsonResponse
    {
        $data = SignedRequestVerifier::decode((string) $request->input('signed_request'), $secret);

        if ($data === null) {
            return response()->json(['error' => 'signed_request tidak valid.'], 400);
        }

        $userId = (string) ($data['user_id'] ?? '');
        $confirmationCode = (string) Str::uuid();

        // Sama seperti deauthorize di atas: hanya jalur Instagram yang bisa
        // dicocokkan langsung ke baris `social_accounts` lewat `user_id`.
        if ($platform === SocialAccount::PLATFORM_INSTAGRAM && $userId !== '') {
            SocialAccount::where('platform', $platform)
                ->where('platform_account_id', $userId)
                ->get()
                ->each(fn (SocialAccount $account) => $account->delete());
        }

        Log::info('Permintaan hapus data lewat webhook Meta', [
            'platform' => $platform,
            'user_id' => $userId,
            'kode_konfirmasi' => $confirmationCode,
        ]);

        return response()->json([
            'url' => route('oauth.data-deletion-status', ['id' => $confirmationCode]),
            'confirmation_code' => $confirmationCode,
        ]);
    }

    /** Halaman publik yang ditunjuk Meta agar pengguna bisa mengecek status permintaannya. */
    public function dataDeletionStatus(Request $request): View
    {
        return view('legal.data-deletion-status', [
            'kode' => (string) $request->query('id', ''),
        ]);
    }
}

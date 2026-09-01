<?php

namespace App\Services\Meta;

/**
 * Memverifikasi `signed_request` yang dikirim Meta ke webhook Deauthorize
 * Callback dan Data Deletion Request (§14 — wajib untuk App Review).
 *
 * Formatnya: base64url(signature).base64url(payload JSON), ditandatangani
 * HMAC-SHA256 memakai App Secret — BUKAN access token. Karena hanya Meta dan
 * pemilik App Secret yang bisa menghasilkan tanda tangan yang cocok, payload
 * yang lolos verifikasi ini dijamin benar-benar berasal dari Meta.
 */
class SignedRequestVerifier
{
    /**
     * @return array<string, mixed>|null null bila format atau tanda tangannya tidak valid.
     */
    public static function decode(string $signedRequest, string $secret): ?array
    {
        if ($secret === '') {
            return null;
        }

        $parts = explode('.', $signedRequest, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedSig, $encodedPayload] = $parts;

        $sig = self::base64UrlDecode($encodedSig);
        $payload = self::base64UrlDecode($encodedPayload);

        if ($sig === false || $payload === false) {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return null;
        }

        // Meta hanya pernah memakai HMAC-SHA256, tapi field algoritmanya tetap
        // dicek — menolak diam-diam kalau suatu saat berubah, bukan memproses
        // payload yang tidak lagi cocok cara verifikasinya.
        if (mb_strtoupper((string) ($data['algorithm'] ?? '')) !== 'HMAC-SHA256') {
            return null;
        }

        $expected = hash_hmac('sha256', $encodedPayload, $secret, true);

        if (! hash_equals($expected, $sig)) {
            return null;
        }

        return $data;
    }

    private static function base64UrlDecode(string $value): string|false
    {
        // Tanpa mode strict — signed_request Meta memakai base64url TANPA
        // padding `=`, dan base64_decode() menerimanya asal karakternya valid.
        return base64_decode(strtr($value, '-_', '+/'));
    }
}

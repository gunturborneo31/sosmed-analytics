<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan kabupaten dalam bentuk kunci–nilai.
 *
 * Dibaca di hampir setiap permintaan halaman Rekap, jadi nilainya di-cache dan
 * cache-nya dibuang tiap kali disimpan — bukan dibiarkan kedaluwarsa sendiri,
 * supaya perubahan admin langsung terlihat oleh admin lain.
 */
#[Fillable(['key', 'value', 'updated_by'])]
class Setting extends Model
{
    /** Kunci penyebut IKK — jumlah penduduk menurut BPS/Dukcapil. */
    public const JUMLAH_PENDUDUK = 'ikk.jumlah_penduduk';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    private const CACHE_PREFIX = 'pengaturan:';

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            self::CACHE_PREFIX.$key,
            fn () => self::query()->whereKey($key)->value('value') ?? false,
        );

        return $value === false ? $default : $value;
    }

    public static function put(string $key, mixed $value, ?string $userId = null): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'updated_by' => $userId],
        );

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Penyebut IKK yang sedang berlaku — nilai tersimpan bila ada, kalau belum
     * pernah disimpan jatuh ke bawaan di config/ikk.php.
     */
    public static function jumlahPenduduk(): int
    {
        return (int) self::get(self::JUMLAH_PENDUDUK, config('ikk.jumlah_penduduk'));
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

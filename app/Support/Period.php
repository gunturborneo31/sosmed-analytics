<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Rentang waktu yang dipakai seluruh filter rekap (§10.2).
 */
final readonly class Period
{
    /**
     * Pilihan periode. Kuncinya jumlah hari, dipakai apa adanya oleh
     * {@see self::fromKey()} — menambah opsi cukup menambah baris di sini.
     *
     * Batas 1 tahun bukan angka sembarang: Meta menyimpan insight harian
     * paling lama 2 tahun, dan riwayat yang ditarik aplikasi ini 365 hari.
     */
    public const OPTIONS = [
        '7' => '7 hari terakhir',
        '30' => '30 hari terakhir',
        '90' => '90 hari terakhir',
        '365' => '1 tahun terakhir',
        'custom' => 'Rentang kustom',
    ];

    public function __construct(
        public string $key,
        public CarbonImmutable $from,
        public CarbonImmutable $until,
    ) {}

    public static function fromKey(string $key, ?string $from = null, ?string $until = null): self
    {
        if ($key === 'custom') {
            if (! $from || ! $until) {
                throw new InvalidArgumentException('Rentang kustom membutuhkan tanggal awal dan akhir.');
            }

            $start = CarbonImmutable::parse($from)->startOfDay();
            $end = CarbonImmutable::parse($until)->startOfDay();

            return new self('custom', $start->min($end), $start->max($end));
        }

        if (! array_key_exists($key, self::OPTIONS)) {
            $key = '30';
        }

        $days = (int) $key;
        $end = CarbonImmutable::today();

        return new self($key, $end->subDays($days - 1), $end);
    }

    public static function default(): self
    {
        return self::fromKey('30');
    }

    public function days(): int
    {
        return $this->from->diffInDays($this->until) + 1;
    }

    /** Rentang sebelumnya dengan panjang sama — pembanding untuk delta. */
    public function previous(): self
    {
        $days = $this->days();

        return new self(
            $this->key,
            $this->from->subDays($days),
            $this->from->subDay(),
        );
    }

    public function label(): string
    {
        return $this->key === 'custom'
            ? $this->from->translatedFormat('j M Y').' – '.$this->until->translatedFormat('j M Y')
            : self::OPTIONS[$this->key];
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function untilDate(): string
    {
        return $this->until->toDateString();
    }
}

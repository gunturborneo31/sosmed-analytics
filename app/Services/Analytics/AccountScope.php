<?php

namespace App\Services\Analytics;

use App\Models\SocialAccount;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Filter §10.2 diterjemahkan jadi satu subquery berisi ID akun yang boleh dihitung.
 * Dipakai bersama oleh seluruh query rekap agar aturan filternya cuma ditulis sekali.
 */
final class AccountScope
{
    private ?string $unitType = null;

    private ?string $platform = null;

    /** @var list<string> */
    private array $unitIds = [];

    private bool $connectedOnly = true;

    public static function make(): self
    {
        return new self;
    }

    public function unitType(?string $type): self
    {
        $this->unitType = $type ?: null;

        return $this;
    }

    public function platform(?string $platform): self
    {
        $this->platform = $platform ?: null;

        return $this;
    }

    /** @param list<string>|array<int, string> $ids */
    public function units(array $ids): self
    {
        $this->unitIds = array_values(array_filter(array_map('strval', $ids)));

        return $this;
    }

    public function includeDisconnected(): self
    {
        $this->connectedOnly = false;

        return $this;
    }

    /** Platform yang sedang disaring, atau null bila seluruh platform ikut. */
    public function platformFilter(): ?string
    {
        return $this->platform;
    }

    /** @return list<string> ID OPD yang dipilih; kosong berarti seluruh kabupaten. */
    public function unitFilter(): array
    {
        return $this->unitIds;
    }

    /**
     * Salinan dengan platform yang berbeda, filter lainnya tetap.
     *
     * Dipakai laporan untuk memecah angka gabungan per platform tanpa merusak
     * cakupan yang sedang berlaku — objek ini mutable, jadi menyetel platform
     * langsung pada instance yang sama akan mengubah hasil pemanggil lain.
     */
    public function withPlatform(?string $platform): self
    {
        $salinan = new self;
        $salinan->unitType = $this->unitType;
        $salinan->unitIds = $this->unitIds;
        $salinan->connectedOnly = $this->connectedOnly;
        $salinan->platform = $platform ?: null;

        return $salinan;
    }

    /** @return Builder<\stdClass> */
    public function query(): Builder
    {
        return DB::table('social_accounts as sa')
            ->join('organizational_units as ou', 'ou.id', '=', 'sa.organizational_unit_id')
            ->when($this->connectedOnly, fn ($q) => $q->where('sa.status', SocialAccount::STATUS_CONNECTED))
            ->when($this->unitType, fn ($q, $type) => $q->where('ou.type', $type))
            ->when($this->platform, fn ($q, $platform) => $q->where('sa.platform', $platform))
            ->when($this->unitIds, fn ($q, $ids) => $q->whereIn('ou.id', $ids))
            ->where('ou.is_active', true);
    }

    /** @return Builder<\stdClass> ID akun saja — untuk whereIn di query lain. */
    public function accountIds(): Builder
    {
        return $this->query()->select('sa.id');
    }

    /**
     * Perangkat daerah yang tercakup filter ini, terlepas dari apakah OPD-nya
     * sudah punya akun medsos atau belum.
     *
     * Dipakai sebagai penyebut "X dari Y OPD". Filter platform sengaja tidak
     * diterapkan: memilih "Instagram" mempersempit akun yang dihitung, bukan
     * jumlah perangkat daerah yang ada.
     *
     * @return Builder<\stdClass>
     */
    public function unitQuery(): Builder
    {
        return DB::table('organizational_units as ou')
            ->where('ou.is_active', true)
            ->when($this->unitType, fn ($q, $type) => $q->where('ou.type', $type))
            ->when($this->unitIds, fn ($q, $ids) => $q->whereIn('ou.id', $ids));
    }
}

<?php

namespace App\Livewire\Admin;

use App\Jobs\DispatchAllAccountSyncs;
use App\Models\OrganizationalUnit;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use App\Services\Analytics\CountyAnalytics;
use App\Support\Period;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Perangkat Daerah')]
class UnitDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'cari')]
    public string $search = '';

    #[Url(as: 'jenis')]
    public string $unitType = '';

    #[Url(as: 'status')]
    public string $status = '';

    /** Formulir */
    public bool $showForm = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $type = 'dinas';

    public string $district = '';

    public string $contactPerson = '';

    public string $contactPhone = '';

    public bool $isActive = true;

    /** Konfirmasi hapus — dialog bawaan browser diganti modal aplikasi. */
    public bool $confirmingDelete = false;

    public ?string $deletingId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'unitType', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(OrganizationalUnit::TYPES)],
            // District hanya bermakna untuk kecamatan, dan di situ wajib —
            // Peta Denyut mencocokkan kecamatan lewat kolom ini.
            'district' => ['nullable', 'required_if:type,kecamatan', 'string', 'max:100'],
            'contactPerson' => ['nullable', 'string', 'max:120'],
            'contactPhone' => ['nullable', 'string', 'max:30'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Nama perangkat daerah wajib diisi.',
            'name.max' => 'Nama terlalu panjang, maksimal 150 karakter.',
            'type.required' => 'Jenis perangkat daerah wajib dipilih.',
            'type.in' => 'Jenis perangkat daerah tidak dikenali.',
            'district.required_if' => 'Nama kecamatan wajib diisi untuk jenis kecamatan.',
        ];
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->reset('editingId', 'name', 'district', 'contactPerson', 'contactPhone');
        $this->type = 'dinas';
        $this->isActive = true;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeManage();

        $unit = OrganizationalUnit::findOrFail($id);

        $this->editingId = $unit->id;
        $this->name = $unit->name;
        $this->type = $unit->type;
        $this->district = $unit->district ?? '';
        $this->contactPerson = $unit->contact_person ?? '';
        $this->contactPhone = $unit->contact_phone ?? '';
        $this->isActive = $unit->is_active;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $data = $this->validate();

        $unit = $this->editingId
            ? OrganizationalUnit::findOrFail($this->editingId)
            : new OrganizationalUnit;

        $unit->fill([
            'name' => $data['name'],
            'type' => $data['type'],
            'district' => $data['type'] === 'kecamatan' ? $data['district'] : null,
            'contact_person' => $data['contactPerson'] ?: null,
            'contact_phone' => $data['contactPhone'] ?: null,
            'is_active' => $data['isActive'],
        ]);

        // Slug dibuat sekali saat OPD dibuat, lalu dibiarkan tetap. Mengubahnya
        // saat nama diperbarui akan mematahkan tautan detail yang sudah dibagikan.
        if (! $unit->exists) {
            $unit->slug = $this->uniqueSlug($data['name']);
        }

        $unit->save();

        $this->dispatch('toast', type: 'success', message: $this->editingId
            ? "Data {$unit->name} diperbarui."
            : "{$unit->name} ditambahkan.");

        $this->showForm = false;
        $this->reset('editingId', 'name', 'district', 'contactPerson', 'contactPhone');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeManage();

        $unit = OrganizationalUnit::findOrFail($id);
        $unit->update(['is_active' => ! $unit->is_active]);

        $this->dispatch('toast', type: 'success', message: $unit->is_active
            ? "{$unit->name} diaktifkan kembali."
            : "{$unit->name} dinonaktifkan — datanya tetap tersimpan.");
    }

    /** Buka modal konfirmasi sebelum benar-benar menghapus. */
    public function confirmDelete(string $id): void
    {
        $this->authorizeManage();

        $this->deletingId = $id;
        $this->confirmingDelete = true;
    }

    /**
     * OPD yang sedang dikonfirmasi untuk dihapus, dipakai modal konfirmasi
     * agar namanya tampil jelas sebelum tindakan dijalankan.
     */
    #[Computed]
    public function deletingUnit(): ?OrganizationalUnit
    {
        return $this->deletingId ? OrganizationalUnit::find($this->deletingId) : null;
    }

    public function delete(string $id): void
    {
        $this->authorizeManage();

        $this->confirmingDelete = false;
        $this->deletingId = null;

        $unit = OrganizationalUnit::withCount(['socialAccounts', 'users'])->findOrFail($id);

        /*
         | Akun medsos ditautkan dengan cascadeOnDelete, dan setiap akun
         | membawa snapshot harian, demografi, serta log sinkronisasinya.
         | Menghapus OPD yang masih punya akun berarti memusnahkan seluruh
         | riwayat itu tanpa bisa dipulihkan — jadi ditolak. Untuk OPD yang
         | sudah tidak dipakai, menonaktifkan adalah jalan yang benar.
         */
        if ($unit->social_accounts_count > 0) {
            $this->dispatch('toast', type: 'error', message: "{$unit->name} masih punya {$unit->social_accounts_count} akun medsos. "
                .'Putuskan akunnya lebih dulu, atau nonaktifkan OPD ini agar riwayat datanya tetap utuh.');

            return;
        }

        if ($unit->users_count > 0) {
            $this->dispatch('toast', type: 'error', message: "{$unit->name} masih menjadi instansi bagi {$unit->users_count} pengguna. "
                .'Pindahkan pengguna itu ke OPD lain lebih dulu.');

            return;
        }

        $nama = $unit->name;
        $unit->delete();

        $this->dispatch('toast', type: 'success', message: "{$nama} dihapus.");
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetErrorBag();
        $this->reset('editingId', 'name', 'district', 'contactPerson', 'contactPhone');
    }

    /**
     * Menarik ulang data seluruh akun yang terhubung.
     *
     * Job penyebarnya menjeda tiap akun 20 detik — Meta membatasi sekitar 200
     * panggilan per jam, dan 43 akun yang menembak bersamaan akan kena batas
     * lalu gagal berjamaah.
     */
    public function syncAll(): void
    {
        abort_unless(auth()->user()->can('trigger-manual-sync'), 403);

        $jumlah = SocialAccount::active()->count();

        if ($jumlah === 0) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada akun terhubung yang bisa disinkronkan.');

            return;
        }

        DispatchAllAccountSyncs::dispatch(SyncLog::TRIGGER_MANUAL);

        $this->dispatch(
            'toast',
            type: 'success',
            message: "Sinkronisasi {$jumlah} akun diantrekan. Prosesnya dijeda 20 detik per akun agar tidak kena batas panggilan Meta — "
                .'pantau hasilnya di Log Sinkronisasi.',
        );
    }

    /**
     * Hal yang perlu didampingi, ditaruh di halaman ini karena di sinilah
     * tindak lanjutnya dikerjakan — bukan di laporan PDF, yang beredar keluar
     * dan tidak seharusnya memuat urusan operasional internal.
     *
     * @return array{unconnected:int, expiring:int, stale:int, failed_syncs:int}
     */
    #[Computed]
    public function attention(): array
    {
        return CountyAnalytics::make(Period::fromKey('30'))->attention();
    }

    /** @return LengthAwarePaginator<int, OrganizationalUnit> */
    #[Computed]
    public function units(): LengthAwarePaginator
    {
        return OrganizationalUnit::query()
            ->withCount([
                'socialAccounts as connected_accounts' => fn ($q) => $q->where('status', 'connected'),
                'socialAccounts as problem_accounts' => fn ($q) => $q->whereIn('status', ['expired', 'revoked', 'error']),
                'socialAccounts as total_accounts',
                'users as total_users',
            ])
            ->when($this->search, fn ($q, $search) => $q->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$search}%"]))
            ->when($this->unitType, fn ($q, $type) => $q->where('type', $type))
            ->when($this->status === 'belum', fn ($q) => $q->unconnected())
            ->when($this->status === 'terhubung', fn ($q) => $q->whereHas('socialAccounts', fn ($a) => $a->where('status', 'connected')))
            ->when($this->status === 'bermasalah', fn ($q) => $q->whereHas('socialAccounts', fn ($a) => $a->whereIn('status', ['expired', 'revoked', 'error'])))
            ->when($this->status === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Nama kecamatan yang dikenali Peta Denyut (§7.4). Dipakai sebagai saran
     * ketik agar penulisan yang meleset tidak membuat titiknya hilang dari peta.
     *
     * @return list<string>
     */
    #[Computed]
    public function knownDistricts(): array
    {
        return array_keys(PulseMap::COORDINATES);
    }

    private function uniqueSlug(string $name): string
    {
        $dasar = Str::slug($name);
        $slug = $dasar;
        $n = 2;

        while (OrganizationalUnit::where('slug', $slug)->exists()) {
            $slug = "{$dasar}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('manage-organizational-units'), 403);
    }

    public function render()
    {
        return view('livewire.admin.unit-directory', [
            'unitTypes' => [
                '' => 'Semua jenis',
                'dinas' => 'Dinas',
                'badan' => 'Badan',
                'kecamatan' => 'Kecamatan',
                'sekretariat' => 'Sekretariat',
            ],
            'formTypes' => [
                'dinas' => 'Dinas',
                'badan' => 'Badan',
                'kecamatan' => 'Kecamatan',
                'sekretariat' => 'Sekretariat',
            ],
            'statuses' => [
                '' => 'Semua status',
                'terhubung' => 'Sudah terhubung',
                'belum' => 'Belum terhubung',
                'bermasalah' => 'Perlu perbaikan',
                'nonaktif' => 'Nonaktif',
            ],
        ]);
    }
}

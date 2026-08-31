<?php

namespace App\Livewire\Admin;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Kelola pengguna (§6, §13) — mencakup super-admin, admin-kominfo, dan operator
 * OPD. Pendaftaran mandiri tidak ada; setiap akun dibuat di sini.
 *
 * Karena data 52 perangkat daerah sudah ada, halaman ini juga bisa membuatkan
 * akun operator sekaligus untuk seluruh OPD yang belum punya login — surel dan
 * kata sandinya dibangkitkan otomatis dari data OPD, bukan diketik satu-satu.
 */
#[Layout('components.layouts.app')]
#[Title('Kelola Pengguna')]
class UserDirectory extends Component
{
    use WithPagination;

    /**
     * Panjang kata sandi yang dibangkitkan aplikasi.
     *
     * Sengaja pendek agar mudah didiktekan lewat telepon atau ditulis di surat
     * penunjukan ke OPD. Konsekuensinya harus disadari: 6 karakter jauh lebih
     * lemah daripada 12. Yang menahannya adalah pembatasan 5 kali percobaan
     * masuk per surel/IP (lihat LoginRequest) — tanpa itu, sandi sependek ini
     * tidak layak dipakai. Operator tetap sebaiknya menggantinya sendiri.
     */
    public const PANJANG_SANDI = 6;

    public string $search = '';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'operator-opd';

    public ?string $organizationalUnitId = null;

    /**
     * Kredensial yang baru saja dibangkitkan, ditampilkan sekali agar admin
     * sempat mencatat/membagikannya — kata sandi tidak bisa dibaca lagi
     * setelah tersimpan sebagai hash.
     *
     * @var list<array{name: string, email: string, password: string, unit: string}>
     */
    public array $justGenerated = [];

    /** Konfirmasi hapus — dialog bawaan browser diganti modal aplikasi. */
    public bool $confirmingDelete = false;

    public ?string $deletingId = null;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:'.self::PANJANG_SANDI],
            'role' => ['required', Rule::in(Role::pluck('name')->all())],
            'organizationalUnitId' => ['nullable', 'exists:organizational_units,id'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Surel wajib diisi.',
            'email.unique' => 'Surel itu sudah dipakai pengguna lain.',
            'password.required' => 'Kata sandi awal wajib diisi.',
            'password.min' => 'Kata sandi minimal '.self::PANJANG_SANDI.' karakter.',
        ];
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->reset('editingId', 'name', 'email', 'password', 'organizationalUnitId');
        $this->role = 'operator-opd';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeManage();

        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->getRoleNames()->first() ?? 'operator-opd';
        $this->organizationalUnitId = $user->organizational_unit_id;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();

        // Pilihan "Tanpa OPD" mengirim string kosong; disamakan dengan null
        // supaya lolos aturan `nullable` dan tidak ditolak kunci asing.
        if ($this->organizationalUnitId === '') {
            $this->organizationalUnitId = null;
        }

        $data = $this->validate();

        $user = User::updateOrCreate(
            ['id' => $this->editingId],
            array_filter([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'] ?: null,
                'organizational_unit_id' => $data['organizationalUnitId'],
            ], fn ($value, $key) => $value !== null || $key === 'organizational_unit_id', ARRAY_FILTER_USE_BOTH),
        );

        $user->syncRoles([$data['role']]);

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $this->editingId ? 'Data pengguna diperbarui.' : 'Pengguna baru dibuat.');
        $this->reset('editingId', 'name', 'email', 'password', 'organizationalUnitId');
    }

    /** Buka modal konfirmasi sebelum benar-benar menghapus. */
    public function confirmDelete(string $id): void
    {
        $this->authorizeManage();

        $this->deletingId = $id;
        $this->confirmingDelete = true;
    }

    /**
     * Pengguna yang sedang dikonfirmasi untuk dihapus, dipakai modal agar
     * nama dan surelnya tampil jelas sebelum tindakan dijalankan.
     */
    #[Computed]
    public function deletingUser(): ?User
    {
        return $this->deletingId ? User::with('organizationalUnit')->find($this->deletingId) : null;
    }

    public function delete(string $id): void
    {
        $this->authorizeManage();

        $this->confirmingDelete = false;
        $this->deletingId = null;

        if ($id === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'Kamu tidak bisa menghapus akunmu sendiri.');

            return;
        }

        User::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Pengguna dihapus.');
    }

    /**
     * Isi surel & kata sandi pada formulir yang sedang terbuka berdasarkan OPD
     * yang dipilih — admin tetap bisa meninjau atau mengubahnya sebelum simpan.
     */
    public function autofillCredentials(): void
    {
        $this->authorizeManage();

        if (! $this->organizationalUnitId) {
            $this->addError('organizationalUnitId', 'Pilih perangkat daerah dulu agar surelnya bisa dibuatkan.');

            return;
        }

        $unit = OrganizationalUnit::findOrFail($this->organizationalUnitId);

        $this->name = $this->name ?: "Operator {$unit->name}";
        $this->email = $this->uniqueEmailFor($unit);
        $this->password = $this->buatSandi();
        $this->resetErrorBag(['organizationalUnitId', 'email', 'password']);
    }

    /**
     * Isi ulang hanya kolom kata sandi pada formulir yang sedang terbuka.
     * Berlaku baik saat menambah maupun mengubah pengguna.
     */
    public function generatePassword(): void
    {
        $this->authorizeManage();

        $this->password = $this->buatSandi();
        $this->resetErrorBag('password');
    }

    /**
     * Tanpa simbol dan spasi: kata sandi ini kerap didiktekan lewat telepon
     * atau ditulis tangan, dan karakter seperti | ~ ' mudah salah dibaca.
     */
    private function buatSandi(): string
    {
        return Str::password(self::PANJANG_SANDI, symbols: false, spaces: false);
    }

    /**
     * Buatkan satu akun operator langsung dari daftar "OPD belum punya akun",
     * tanpa membuka formulir.
     */
    public function generateForUnit(string $unitId): void
    {
        $this->authorizeManage();

        $unit = OrganizationalUnit::findOrFail($unitId);

        $this->justGenerated[] = $this->createOperatorAccount($unit);

        $this->dispatch('toast', type: 'success', message: "Akun untuk {$unit->name} dibuat. Salin kata sandinya sebelum meninggalkan halaman ini.");
    }

    /**
     * Buatkan akun operator sekaligus untuk seluruh OPD aktif yang belum
     * punya satu pun pengguna terdaftar.
     */
    public function generateForAllUnits(): void
    {
        $this->authorizeManage();

        // Akses properti (bukan pemanggilan method) — Livewire menahan
        // pemanggilan langsung ke method ber-#[Computed] saat sedang berada
        // di dalam sebuah action.
        $unitsTanpaAkun = $this->unitsWithoutAccount;

        if ($unitsTanpaAkun->isEmpty()) {
            $this->dispatch('toast', type: 'success', message: 'Semua perangkat daerah sudah punya akun.');

            return;
        }

        foreach ($unitsTanpaAkun as $unit) {
            $this->justGenerated[] = $this->createOperatorAccount($unit);
        }

        $this->dispatch('toast', type: 'success', message: count($this->justGenerated).' akun dibuat. Salin kata sandinya sebelum meninggalkan halaman ini.');
    }

    public function dismissGenerated(): void
    {
        $this->justGenerated = [];
    }

    /**
     * @return array{name: string, email: string, password: string, unit: string}
     */
    private function createOperatorAccount(OrganizationalUnit $unit): array
    {
        $name = "Operator {$unit->name}";
        $email = $this->uniqueEmailFor($unit);
        $password = $this->buatSandi();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'organizational_unit_id' => $unit->id,
        ]);

        $user->assignRole('operator-opd');

        return ['name' => $name, 'email' => $email, 'password' => $password, 'unit' => $unit->name];
    }

    /**
     * Surel dibentuk dari slug OPD + domain akun (§ config app.opd_account_domain).
     * Slug OPD sudah unik, tapi diberi penjaga bertingkat kalau-kalau surel itu
     * sudah dipakai akun lain yang dibuat manual.
     */
    private function uniqueEmailFor(OrganizationalUnit $unit): string
    {
        $domain = config('app.opd_account_domain');
        $dasar = "{$unit->slug}@{$domain}";

        if (! User::where('email', $dasar)->exists()) {
            return $dasar;
        }

        $n = 2;

        while (User::where('email', "{$unit->slug}-{$n}@{$domain}")->exists()) {
            $n++;
        }

        return "{$unit->slug}-{$n}@{$domain}";
    }

    /** @return Collection<int, OrganizationalUnit> */
    #[Computed]
    public function unitsWithoutAccount(): Collection
    {
        return OrganizationalUnit::active()
            ->whereDoesntHave('users')
            ->orderBy('name')
            ->get();
    }

    /**
     * Route /admin/pengguna sudah dijaga middleware `can:manage-users`, tapi
     * komponen Livewire bisa dipanggil langsung tanpa melewati middleware itu
     * (mis. lewat pengujian atau permintaan AJAX tersusun) — jadi penjagaan
     * diulang di sini sebagai lapis kedua, sama seperti UnitDirectory.
     */
    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('manage-users'), 403);
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with(['organizationalUnit', 'roles'])
            ->when($this->search, fn ($q, $search) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$search}%"])
                ->orWhereRaw('LOWER(email) LIKE LOWER(?)', ["%{$search}%"])))
            ->orderBy('name')
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.admin.user-directory', [
            'roles' => Role::pluck('name', 'name')->all(),
            'units' => ['' => 'Tanpa OPD'] + OrganizationalUnit::active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}

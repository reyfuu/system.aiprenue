<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['owner' => 'Owner', 'manager' => 'Manager', 'it' => 'IT', 'admin' => 'Admin', 'staff' => 'Staff'];

    /**
     * Menu yang boleh diakses tiap role. '*' = semua.
     * Menu: dashboard, pipeline, kanban, order, mindmap, script, pembukuan, user, prodpilot.
     * - prodpilot = tautan EKSTERNAL (bukan route app), jadi tak ada di EnsureMenuAccess —
     *   gerbangnya cuma tampil/tidaknya menu di Sidebar. owner/it ('*') + manager saja.
     * - owner & it = akses penuh (termasuk manajemen user).
     * - manager = kelola board+task+operasional, TAPI tak boleh menu 'user'.
     * - admin = kelola (CRUD, lihat canManage()) TAPI cuma di sales/kanban/mindmap.
     * - staff = VIEW-ONLY (lihat canManage()): tanpa menu 'user' & 'pembukuan' (keuangan).
     */
    public const MENU_ACCESS = [
        'owner'   => ['*'],
        'it'      => ['*'],           // IT = akses penuh teknis
        'manager' => ['dashboard', 'pipeline', 'kanban', 'order', 'mindmap', 'script', 'pembukuan', 'insight', 'prodpilot', 'akses'],
        'admin'   => ['pipeline', 'kanban', 'mindmap', 'insight'],   // sales(=pipeline)/kanban/mindmap, boleh CRUD
        'staff'   => ['kanban', 'mindmap'],   // view-only, cuma dua menu ini
    ];

    /** Semua menu yang bisa diatur di halaman Manajemen Akses (key => label).
     *  Menambah menu baru di aplikasi? Daftarkan di sini juga, kalau tidak menu
     *  itu tak akan pernah muncul di halaman pengaturan. */
    public const MENUS = [
        'dashboard' => 'Dashboard',
        'pipeline'  => 'Sales',
        'kanban'    => 'Kanban',
        'order'     => 'Order',
        'mindmap'   => 'Mindmap',
        'script'    => 'Script',
        'pembukuan' => 'Pembukuan',
        'user'      => 'User',
        'insight'   => 'Insight',
        'prodpilot' => 'ProdPilot',
        'akses'     => 'Manajemen Akses',
    ];

    /** Cache per-instance: `menus` dibangun dgn ~10 kali canSee() pada user yang
     *  sama, jadi satu query per request sudah cukup. Sengaja BUKAN static —
     *  cache static bocor antar-tes (DB di-refresh, cache tidak). */
    private ?array $izinCache = null;

    /** Daftar menu yang boleh dilihat peran ini, dari DB. null = tabelnya belum
     *  ada / peran ini tak punya baris sama sekali → pemanggil pakai aturan kode. */
    private function izinDariDb(): ?array
    {
        if ($this->izinCache !== null) {
            return $this->izinCache ?: null;
        }
        try {
            $rows = \Illuminate\Support\Facades\DB::table('role_menu_access')
                ->where('role', $this->role)->pluck('menu')->all();
        } catch (\Throwable) {
            return $this->izinCache = null;   // migrasi belum jalan → jangan pecah
        }

        $this->izinCache = $rows;

        return $rows ?: null;
    }

    /** Apakah role user boleh melihat menu tertentu.
     *
     *  Owner SELALU true & tak bisa dicabut lewat halaman Manajemen Akses.
     *  Itu pagar anti-kekunci: tanpa ini, satu centang yang salah bisa membuat
     *  TAK ADA seorang pun yang masih bisa membuka halaman pengaturannya, dan
     *  satu-satunya jalan keluar adalah mengedit database langsung.
     *
     *  Sumber kebenaran = tabel `role_menu_access`. Kalau tabelnya belum ada
     *  (migrasi belum jalan) atau peran itu belum punya baris, jatuh ke
     *  MENU_ACCESS di kode supaya perilaku lama tetap berlaku.
     */
    public function canSee(string $menu): bool
    {
        if ($this->role === 'owner') {
            return true;
        }

        if (($izin = $this->izinDariDb()) !== null) {
            return in_array($menu, $izin, true);
        }

        $allowed = self::MENU_ACCESS[$this->role] ?? [];

        return in_array('*', $allowed, true) || in_array($menu, $allowed, true);
    }

    /** Boleh CRUD / kelola (board, task, order, pembukuan) = tim manajemen.
     *  'staff' sengaja TIDAK di sini: view-only, tapi tetap boleh berkomentar
     *  (route comments.* memang tak dicek canManage di EnsureMenuAccess). */
    public function canManage(): bool
    {
        return in_array($this->role, ['owner', 'manager', 'it', 'admin'], true);
    }

    /** Route landing pertama yang boleh diakses user. */
    public function homeRoute(): string
    {
        return match (true) {
            $this->canSee('dashboard') => 'dashboard',
            $this->canSee('script')    => 'script.index',
            $this->canSee('kanban')    => 'pipelines.kanban',
            default                    => 'pipelines.kanban',
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

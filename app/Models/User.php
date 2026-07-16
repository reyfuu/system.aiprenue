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

    public const ROLES = ['owner' => 'Owner', 'manager' => 'Manager', 'it' => 'IT', 'staff' => 'Staff'];

    /**
     * Menu yang boleh diakses tiap role. '*' = semua.
     * Menu: dashboard, pipeline, kanban, order, mindmap, script, pembukuan, user.
     * - owner & it = akses penuh (termasuk manajemen user).
     * - manager = kelola board+task+operasional, TAPI tak boleh menu 'user'.
     * - staff = VIEW-ONLY (lihat canManage()): tanpa menu 'user' & 'pembukuan' (keuangan).
     */
    public const MENU_ACCESS = [
        'owner'   => ['*'],
        'it'      => ['*'],           // IT = akses penuh teknis
        'manager' => ['dashboard', 'pipeline', 'kanban', 'order', 'mindmap', 'script', 'pembukuan'],
        'staff'   => ['kanban', 'mindmap'],   // view-only, cuma dua menu ini
    ];

    /** Apakah role user boleh melihat menu tertentu. */
    public function canSee(string $menu): bool
    {
        $allowed = self::MENU_ACCESS[$this->role] ?? [];

        return in_array('*', $allowed, true) || in_array($menu, $allowed, true);
    }

    /** Boleh CRUD / kelola (board, task, order, pembukuan) = tim manajemen.
     *  'staff' sengaja TIDAK di sini: view-only, tapi tetap boleh berkomentar
     *  (route comments.* memang tak dicek canManage di EnsureMenuAccess). */
    public function canManage(): bool
    {
        return in_array($this->role, ['owner', 'manager', 'it'], true);
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

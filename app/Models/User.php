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

    public const ROLES = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'it' => 'IT', 'staff' => 'Staff', 'editor' => 'Editor'];

    /**
     * Menu yang boleh diakses tiap role. '*' = semua.
     * Menu: dashboard, pipeline, kanban, script, pembukuan, user.
     */
    public const MENU_ACCESS = [
        'super_admin' => ['*'],
        'it'          => ['*'],           // IT setara super admin
        'admin'       => ['script', 'kanban'],
        'editor'      => ['kanban'],
        'staff'       => ['kanban'],      // default (tidak disebut eksplisit)
    ];

    /** Apakah role user boleh melihat menu tertentu. */
    public function canSee(string $menu): bool
    {
        $allowed = self::MENU_ACCESS[$this->role] ?? [];

        return in_array('*', $allowed, true) || in_array($menu, $allowed, true);
    }

    /** Boleh CRUD / kelola (kanban board, tasks). Hanya super admin & IT. */
    public function canManage(): bool
    {
        return in_array($this->role, ['super_admin', 'it'], true);
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

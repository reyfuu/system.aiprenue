<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();
        $user = $request->user();

        // Route mutasi (CRUD kartu/board) hanya untuk super admin & IT.
        if ($name !== null && $this->isManageRoute($name)) {
            if (! $user || ! $user->canManage()) {
                abort(403, 'Hanya super admin & IT yang bisa mengubah data ini.');
            }
        }

        $menus = $this->menusFor($name);

        // route tanpa pemetaan (mis. logout) = tidak dibatasi
        if ($menus === null) {
            return $next($request);
        }

        // boleh jika user bisa lihat salah satu menu terkait route
        foreach ($menus as $menu) {
            if ($user && $user->canSee($menu)) {
                return $next($request);
            }
        }

        abort(403, 'Anda tidak punya akses ke halaman ini.');
    }

    /** Route yang mengubah data (create/update/delete) → butuh canManage. */
    private function isManageRoute(string $name): bool
    {
        return in_array($name, [
            'pipelines.store', 'pipelines.update', 'pipelines.destroy',
            'pipelines.progress', 'pipelines.todos', 'pipelines.archive',
        ], true)
            || str_starts_with($name, 'boards.')
            || str_starts_with($name, 'columns.')
            || str_starts_with($name, 'attachments.'); // komentar TIDAK di sini (staff boleh)
    }

    /** Route name → menu terkait (array; null = bebas). */
    private function menusFor(?string $name): ?array
    {
        if ($name === null) {
            return null;
        }

        return match (true) {
            $name === 'dashboard' => ['dashboard'],
            in_array($name, ['pipelines.kanban', 'pipelines.progress', 'pipelines.todos', 'pipelines.archive'], true) => ['kanban'],
            $name === 'pipelines.store' => ['kanban', 'pipeline'], // dipakai kanban & tabel
            str_starts_with($name, 'boards.') => ['kanban'],
            str_starts_with($name, 'columns.') => ['kanban'],
            str_starts_with($name, 'comments.') => ['kanban'],     // komentar: cukup akses kanban
            str_starts_with($name, 'attachments.') => ['kanban'],  // lampiran (manage dicek terpisah)
            str_starts_with($name, 'pipelines.') => ['pipeline'],
            str_starts_with($name, 'script.') => ['script'],
            str_starts_with($name, 'pembukuan.') => ['pembukuan'],
            str_starts_with($name, 'users.') => ['user'],
            default => null,
        };
    }
}

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
            $menuKelola = str_starts_with($name, 'content.') ? 'content' : null;
            $bolehKelola = $menuKelola ? $user?->canManageMenu($menuKelola) : $user?->canManage();

            if (! $bolehKelola) {
                abort(403, 'Anda tidak punya izin untuk mengubah data ini.');
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
            'pipelines.reorder', 'pipelines.todos', 'pipelines.archive',
            'pipelines.done',   // mutasi juga — sebelumnya lolos cek canManage()
        ], true)
            || str_starts_with($name, 'boards.')
            || str_starts_with($name, 'columns.')
            || str_starts_with($name, 'attachments.') // komentar TIDAK di sini (staff boleh)
            || str_starts_with($name, 'transactions.')
            || str_starts_with($name, 'inventories.')
            || in_array($name, ['content.store', 'content.update', 'content.destroy'], true)
            || $name === 'akses.update'          // ubah hak akses = mutasi
            || in_array($name, ['orders.store', 'orders.update', 'orders.destroy'], true)
            // mindmaps.index/show TIDAK di sini — semua peran boleh lihat galeri & editor.
            // Sebelumnya mutasinya lolos: tombolnya disembunyikan di Vue lewat `canManage`,
            // tapi request langsung tetap tembus.
            || in_array($name, ['mindmaps.store', 'mindmaps.update', 'mindmaps.destroy'], true);
    }

    /** Route name → menu terkait (array; null = bebas). */
    private function menusFor(?string $name): ?array
    {
        if ($name === null) {
            return null;
        }

        // Sales Pipeline & Kanban kini sama-sama board (Kanban.vue) di atas model Pipeline,
        // jadi route kartu/board/kolom dipakai KEDUA menu — cukup punya salah satunya.
        return match (true) {
            $name === 'dashboard' => ['dashboard'],
            $name === 'pipelines.kanban' => ['kanban'],
            $name === 'pipelines.index' => ['pipeline'],
            in_array($name, ['pipelines.reorder', 'pipelines.todos', 'pipelines.archive', 'pipelines.done'], true) => ['kanban', 'pipeline'],
            $name === 'pipelines.store' => ['kanban', 'pipeline'],
            str_starts_with($name, 'boards.') => ['kanban', 'pipeline'],
            str_starts_with($name, 'columns.') => ['kanban', 'pipeline'],
            str_starts_with($name, 'comments.') => ['kanban', 'pipeline'],     // komentar kartu
            str_starts_with($name, 'attachments.') => ['kanban', 'pipeline'],  // lampiran (manage dicek terpisah)
            str_starts_with($name, 'pipelines.') => ['pipeline'],
            str_starts_with($name, 'orders.') => ['order'],
            str_starts_with($name, 'mindmaps.') => ['mindmap'],
            str_starts_with($name, 'script.') => ['script'],
            str_starts_with($name, 'content.') => ['content'],
            str_starts_with($name, 'pembukuan.') => ['pembukuan'],
            str_starts_with($name, 'transactions.') => ['pembukuan'],
            str_starts_with($name, 'inventories.') => ['pembukuan'],
            str_starts_with($name, 'users.') => ['user'],
            str_starts_with($name, 'insight.') => ['insight'],
            str_starts_with($name, 'upload.') => ['upload'],
            str_starts_with($name, 'akses.') => ['akses'],   // Manajemen Akses
            default => null,
        };
    }
}

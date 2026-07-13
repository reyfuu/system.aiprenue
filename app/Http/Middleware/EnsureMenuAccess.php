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
        $menus = $this->menusFor($name);

        // route tanpa pemetaan (mis. logout) = tidak dibatasi
        if ($menus === null) {
            return $next($request);
        }

        $user = $request->user();
        // boleh jika user bisa lihat salah satu menu terkait route
        foreach ($menus as $menu) {
            if ($user && $user->canSee($menu)) {
                return $next($request);
            }
        }

        abort(403, 'Anda tidak punya akses ke halaman ini.');
    }

    /** Route name → menu terkait (array; null = bebas). */
    private function menusFor(?string $name): ?array
    {
        if ($name === null) {
            return null;
        }

        return match (true) {
            $name === 'dashboard' => ['dashboard'],
            in_array($name, ['pipelines.kanban', 'pipelines.progress', 'pipelines.todos'], true) => ['kanban'],
            $name === 'pipelines.store' => ['kanban', 'pipeline'], // dipakai kanban & tabel
            str_starts_with($name, 'pipelines.') => ['pipeline'],
            str_starts_with($name, 'script.') => ['script'],
            str_starts_with($name, 'pembukuan.') => ['pembukuan'],
            str_starts_with($name, 'users.') => ['user'],
            default => null,
        };
    }
}

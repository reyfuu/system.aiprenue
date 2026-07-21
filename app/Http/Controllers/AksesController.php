<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Halaman Manajemen Akses: centang menu apa saja yang boleh dilihat tiap peran.
 *  Menggantikan konstanta `User::MENU_ACCESS` sbg sumber kebenaran (lihat
 *  User::canSee) — supaya aturan bisa diubah tanpa deploy ulang. */
class AksesController extends Controller
{
    public function index()
    {
        return Inertia::render('Akses', [
            'roles' => User::ROLES,
            'menus' => User::MENUS,
            // matriks: role => [menu yang dicentang]
            'akses' => collect(array_keys(User::ROLES))
                ->mapWithKeys(fn ($role) => [
                    $role => DB::table('role_menu_access')->where('role', $role)->pluck('menu')->all(),
                ]),
            // Daftar menu yang levelnya CRUD. UI saat ini menampilkan pilihan
            // tiga level khusus Content; bentuk generik ini siap dipakai menu lain.
            'kelola' => collect(array_keys(User::ROLES))
                ->mapWithKeys(fn ($role) => [
                    $role => DB::table('role_menu_access')->where('role', $role)
                        ->where('can_manage', true)->pluck('menu')->all(),
                ]),
            // owner tak bisa diubah (pagar anti-kekunci di User::canSee)
            'terkunci' => ['owner'],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'akses' => ['required', 'array'],
            'akses.*' => ['array'],
            // Nilai divalidasi ketat: satu menu asal-asalan yang lolos = baris
            // sampah yang tak pernah cocok dgn menu mana pun & tak bisa dihapus
            // dari halaman ini (karena kolomnya tak dirender).
            'akses.*.*' => [Rule::in(array_keys(User::MENUS))],
            'kelola' => ['nullable', 'array'],
            'kelola.*' => ['array'],
            'kelola.*.*' => [Rule::in(array_keys(User::MENUS))],
        ]);

        // Peran tak dikenal dibuang, bukan disimpan: ROLES bisa menyusut, dan
        // baris yatim akan hidup terus tanpa pernah tampil di halaman.
        $bersih = collect($data['akses'])
            ->only(array_keys(User::ROLES))
            ->reject(fn ($_, $role) => in_array($role, ['owner'], true))  // owner: selalu penuh, abaikan kiriman
            ->map(function ($menus, $role) {
                // Pembukuan adalah izin tetap: Manager selalu dapat, role lain tidak.
                $menus = array_values(array_diff($menus, ['pembukuan']));

                return $role === 'manager' ? [...$menus, 'pembukuan'] : $menus;
            });
        $kelola = collect($data['kelola'] ?? [])->only(array_keys(User::ROLES));

        $now = now();
        DB::transaction(function () use ($bersih, $kelola, $now) {
            foreach ($bersih as $role => $menus) {
                // Ganti-paket per peran: hapus lalu tulis ulang. Menghitung selisih
                // tambah/hapus lebih rumit tanpa manfaat — datanya cuma belasan baris.
                DB::table('role_menu_access')->where('role', $role)->delete();

                $unik = array_values(array_unique($menus));
                if ($unik) {
                    $bolehKelola = $kelola->get($role, []);
                    DB::table('role_menu_access')->insert(array_map(fn ($m) => [
                        'role' => $role,
                        'menu' => $m,
                        'can_manage' => in_array($m, $bolehKelola, true),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $unik));
                }
            }
        });

        return redirect()->route('akses.index')->with('status', 'Hak akses diperbarui.');
    }
}

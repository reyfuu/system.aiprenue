<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // PAGAR PRODUKSI. Seeder ini fixture dev: bikin akun ber-password `password123`
        // yang tertulis terbuka di repo publik, dan menimpa data dummy pipeline/order.
        // Laravel memang menuntut --force di produksi, tapi skrip deploy yang menulis
        // `migrate:fresh --seed --force` akan melewatinya tanpa suara.
        // Akun produksi dibuat manual (tinker), bukan dari sini.
        if (app()->isProduction()) {
            $this->command?->warn('Seeder dilewati: environment produksi. Buat akun lewat tinker.');

            return;
        }

        // Data pipeline diimpor via file SQL (mis. di Hostinger), bukan seeder.
        // Akun admin tetap dibuat agar bisa login setelah fresh install lokal.
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password123'),
                'role' => 'owner',
            ]
        );

        // Contoh anggota tim: manager & it (penanggung jawab kanban)
        foreach ([
            ['name' => 'Rani Manager', 'email' => 'rani@example.com', 'role' => 'manager'],
            ['name' => 'Dimas Manager', 'email' => 'dimas@example.com', 'role' => 'manager'],
            ['name' => 'Audi IT', 'email' => 'audi@example.com', 'role' => 'it'],
        ] as $u) {
            User::updateOrCreate(['email' => $u['email']], [
                'name' => $u['name'],
                'password' => Hash::make('password123'),
                'role' => $u['role'],
            ]);
        }

        $this->call([
            PipelineSeeder::class,
            PembukuanSeeder::class,
            OrderSeeder::class,
        ]);
    }
}

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
        // User::factory(10)->create();

        // Data pipeline diimpor via file SQL (mis. di Hostinger), bukan seeder.
        // Akun admin tetap dibuat agar bisa login setelah fresh install lokal.
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
            ]
        );

        // Beberapa staff/editor untuk penanggung jawab kanban
        foreach ([
            ['name' => 'Rani Staff', 'email' => 'rani@example.com', 'role' => 'staff'],
            ['name' => 'Dimas Editor', 'email' => 'dimas@example.com', 'role' => 'editor'],
            ['name' => 'Putri Staff', 'email' => 'putri@example.com', 'role' => 'staff'],
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
        ]);
    }
}

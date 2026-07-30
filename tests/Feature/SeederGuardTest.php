<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DatabaseSeeder = fixture dev. Akunnya ber-password `password123` yang tertulis
 *  terbuka di repo publik — tak boleh pernah lahir di produksi.
 *
 *  NB: seeder dipanggil langsung, bukan lewat $this->seed() (yg memanggil artisan
 *  db:seed). Artisan sudah punya pagarnya sendiri: ConfirmableTrait minta konfirmasi
 *  di produksi. Yang diuji di sini pagar DI DALAM seeder — lapis yang tetap berdiri
 *  walau skrip deploy menulis `--force`.
 */
class SeederGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_tak_membuat_akun_apa_pun_di_produksi(): void
    {
        app()['env'] = 'production';

        app(DatabaseSeeder::class)->run();

        $this->assertSame(0, User::count(), 'seeder tak boleh bikin akun di produksi');
    }

    public function test_seeder_tetap_jalan_di_luar_produksi(): void
    {
        app(DatabaseSeeder::class)->run();

        $this->assertNotNull(User::where('email', 'admin@example.com')->first());
        $this->assertSame('owner', User::where('email', 'admin@example.com')->value('role'));
    }
}

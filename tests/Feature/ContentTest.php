<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Modul Content: akses menu, CRUD, validasi, dan filter minggu ISO. */
class ContentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'manager'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'comp' => 'AI Preneur',
            'jenis_postingan' => 'Reels',
            'kategori' => 'Edukasi',
            'referensi' => 'https://instagram.com/reel/contoh',
            'inti_pesan' => 'AI membantu bisnis bekerja lebih cepat.',
            'hook_material' => 'Bisnis kamu sudah dibaca AI belum?',
            'brief_original' => 'Brief awal content.',
            'opsi_brief' => 'Versi brief final.',
            'script_remake' => 'Script hasil remake.',
            'editor' => 'Raka',
            'progress' => 'editing',
            'tanggal_upload' => '2026-07-21',
            'link_hasil_editing' => 'https://drive.google.com/editing',
            'link_b_roll' => 'https://drive.google.com/b-roll',
            'caption' => 'Caption content.',
            'link_ai_kata_kunci' => 'https://chatgpt.com/share/contoh',
        ], $override);
    }

    public function test_manager_bisa_crud_content(): void
    {
        $manager = $this->user();

        $this->actingAs($manager)->post('/content', $this->data())->assertRedirect();
        $content = Content::firstOrFail();
        $this->assertSame('AI Preneur', $content->comp);

        $this->actingAs($manager)->put('/content/'.$content->id, $this->data([
            'progress' => 'published',
            'caption' => 'Caption revisi.',
        ]))->assertRedirect();
        $this->assertDatabaseHas('contents', ['id' => $content->id, 'progress' => 'published', 'caption' => 'Caption revisi.']);

        $this->actingAs($manager)->delete('/content/'.$content->id)->assertRedirect();
        $this->assertDatabaseMissing('contents', ['id' => $content->id]);
    }

    public function test_filter_minggu_memakai_senin_sampai_minggu(): void
    {
        Content::create($this->data(['comp' => 'Senin', 'tanggal_upload' => '2026-07-20']));
        Content::create($this->data(['comp' => 'Minggu', 'tanggal_upload' => '2026-07-26']));
        Content::create($this->data(['comp' => 'Minggu berikutnya', 'tanggal_upload' => '2026-07-27']));

        $this->actingAs($this->user())->get('/content?week=2026-W30')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Content/Index')
                ->has('contents.data', 2)
                ->where('filters.week', '2026-W30')
            );
    }

    public function test_progress_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->user())->post('/content', $this->data(['progress' => 'ngawur']))
            ->assertSessionHasErrors('progress');

        $this->assertSame(0, Content::count());
    }

    public function test_staff_tidak_bisa_melihat_atau_mengubah_content(): void
    {
        $staff = $this->user('staff');

        $this->actingAs($staff)->get('/content')->assertForbidden();
        $this->actingAs($staff)->post('/content', $this->data())->assertForbidden();
    }

    public function test_manager_mendapat_menu_content_di_shared_props(): void
    {
        $this->actingAs($this->user())->get('/content')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.menus.content', true));
    }

    public function test_content_bisa_dinonaktifkan_dari_manajemen_akses(): void
    {
        $manager = $this->user();
        DB::table('role_menu_access')->where('role', 'manager')->where('menu', 'content')->delete();

        $this->actingAs($manager)->get('/content')->assertForbidden();
    }

    public function test_level_lihat_saja_bisa_masuk_tapi_tidak_bisa_crud(): void
    {
        $manager = $this->user();
        DB::table('role_menu_access')->where('role', 'manager')->where('menu', 'content')
            ->update(['can_manage' => false]);

        $this->actingAs($manager)->get('/content')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManageContent', false));
        $this->actingAs($manager)->post('/content', $this->data())->assertForbidden();
    }

    public function test_level_crud_bisa_masuk_dan_mengelola(): void
    {
        $manager = $this->user();
        DB::table('role_menu_access')->where('role', 'manager')->where('menu', 'content')
            ->update(['can_manage' => true]);

        $this->actingAs($manager)->get('/content')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManageContent', true));
        $this->actingAs($manager)->post('/content', $this->data())->assertRedirect();
    }
}

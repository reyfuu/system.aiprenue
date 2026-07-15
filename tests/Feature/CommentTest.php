<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\PipelineComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Komentar kartu. Beda dari fitur lain: staff SENGAJA boleh komentar
 *  (comments.* tak masuk isManageRoute) — view-only tapi tetap bisa bersuara. */
class CommentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function card(): Pipeline
    {
        return Pipeline::create([
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal',
            'progress' => 'lead', 'payment_status' => 'belum',
        ]);
    }

    public function test_staff_boleh_menulis_komentar(): void
    {
        $card = $this->card();
        $staff = $this->user('staff');

        $this->actingAs($staff)->post("/pipelines/{$card->id}/comments", ['body' => 'Sudah dihubungi'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pipeline_comments', [
            'pipeline_id' => $card->id,
            'user_id'     => $staff->id,     // penulis = user login, bukan dari request
            'body'        => 'Sudah dihubungi',
        ]);
    }

    public function test_isi_komentar_wajib(): void
    {
        $card = $this->card();

        $this->actingAs($this->user())->post("/pipelines/{$card->id}/comments", ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, PipelineComment::count());
    }

    public function test_penulis_boleh_menghapus_komentarnya_sendiri(): void
    {
        $card = $this->card();
        $staff = $this->user('staff');
        $this->actingAs($staff)->post("/pipelines/{$card->id}/comments", ['body' => 'Punya saya']);

        $this->actingAs($staff)->delete('/comments/'.PipelineComment::first()->id)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, PipelineComment::count());
    }

    public function test_staff_tak_boleh_menghapus_komentar_orang_lain(): void
    {
        $card = $this->card();
        $penulis = $this->user('staff');
        $this->actingAs($penulis)->post("/pipelines/{$card->id}/comments", ['body' => 'Punya orang lain']);

        $this->actingAs($this->user('staff'))   // staff lain
            ->delete('/comments/'.PipelineComment::first()->id)
            ->assertForbidden();

        $this->assertSame(1, PipelineComment::count());
    }

    public function test_manajemen_boleh_menghapus_komentar_siapa_pun(): void
    {
        $card = $this->card();
        $staff = $this->user('staff');
        $this->actingAs($staff)->post("/pipelines/{$card->id}/comments", ['body' => 'Punya staff']);

        $this->actingAs($this->user('it'))->delete('/comments/'.PipelineComment::first()->id)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, PipelineComment::count());
    }
}

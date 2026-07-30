<?php

namespace Tests\Feature;

use App\Models\Output;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CRUD kartu pipeline (store/update/destroy + arsip).
 *  Stage & drag diuji terpisah di SalesPipelineTest. */
class PipelineCardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'owner'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'category' => 'sales', 'account' => 'fk', 'endorse' => 'Deal Baru',
            'progress' => 'lead', 'payment_status' => 'belum',
        ], $o);
    }

    public function test_buat_kartu(): void
    {
        $this->actingAs($this->user())->post('/pipelines', $this->payload(['amount_idr' => 5_000_000]))
            ->assertSessionHasNoErrors();

        $card = Pipeline::first();
        $this->assertSame('Deal Baru', $card->endorse);
        $this->assertSame('lead', $card->progress);
        $this->assertSame('5000000.00', $card->amount_idr);
    }

    public function test_judul_wajib(): void
    {
        $this->actingAs($this->user())->post('/pipelines', $this->payload(['endorse' => '']))
            ->assertSessionHasErrors('endorse');

        $this->assertSame(0, Pipeline::count());
    }

    public function test_account_dan_category_dibatasi_daftar(): void
    {
        $owner = $this->user();

        $this->actingAs($owner)->post('/pipelines', $this->payload(['account' => 'ngawur']))
            ->assertSessionHasErrors('account');
        $this->actingAs($owner)->post('/pipelines', $this->payload(['category' => 'board_hantu']))
            ->assertSessionHasErrors('category');

        $this->assertSame(0, Pipeline::count());
    }

    public function test_link_harus_url_valid(): void
    {
        $this->actingAs($this->user())->post('/pipelines', $this->payload(['link' => 'bukan url']))
            ->assertSessionHasErrors('link');
    }

    public function test_output_ikut_tersinkron(): void
    {
        $owner = $this->user();
        $reels = Output::create(['name' => 'Reels']);
        $story = Output::create(['name' => 'Story']);

        $this->actingAs($owner)->post('/pipelines', $this->payload(['outputs' => [$reels->id, $story->id]]))
            ->assertSessionHasNoErrors();
        $card = Pipeline::first();
        $this->assertEqualsCanonicalizing([$reels->id, $story->id], $card->outputs->pluck('id')->all());

        // update dgn satu output → yang lain terlepas (sync, bukan attach)
        $this->actingAs($owner)->put('/pipelines/'.$card->id, $this->payload(['outputs' => [$story->id]]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$story->id], $card->fresh()->outputs->pluck('id')->all());
    }

    public function test_ubah_kartu(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/pipelines', $this->payload());
        $card = Pipeline::first();

        $this->actingAs($owner)->put('/pipelines/'.$card->id, $this->payload([
            'endorse' => 'Deal Diubah', 'progress' => 'nego', 'amount_usd' => 500,
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Deal Diubah', $card->fresh()->endorse);
        $this->assertSame('nego', $card->fresh()->progress);
        $this->assertSame('500.00', $card->fresh()->amount_usd);
    }

    public function test_hapus_kartu_pakai_soft_delete(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/pipelines', $this->payload());
        $card = Pipeline::first();

        $this->actingAs($owner)->delete('/pipelines/'.$card->id)->assertSessionHasNoErrors();

        $this->assertSame(0, Pipeline::count());
        $this->assertNotNull(Pipeline::withTrashed()->find($card->id), 'kartu harus bisa dipulihkan');
    }

    public function test_arsip_bolak_balik(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/pipelines', $this->payload());
        $card = Pipeline::first();

        $this->actingAs($owner)->patch('/pipelines/'.$card->id.'/archive');
        $this->assertNotNull($card->fresh()->archived_at);

        $this->actingAs($owner)->patch('/pipelines/'.$card->id.'/archive');
        $this->assertNull($card->fresh()->archived_at, 'arsip = toggle');
    }

    public function test_staff_tak_boleh_crud_kartu(): void
    {
        $this->actingAs($this->user())->post('/pipelines', $this->payload());
        $card = Pipeline::first();
        $staff = $this->user('staff');

        $this->actingAs($staff)->post('/pipelines', $this->payload(['endorse' => 'Selundupan']))->assertForbidden();
        $this->actingAs($staff)->put('/pipelines/'.$card->id, $this->payload(['endorse' => 'Dibajak']))->assertForbidden();
        $this->actingAs($staff)->delete('/pipelines/'.$card->id)->assertForbidden();
        $this->actingAs($staff)->patch('/pipelines/'.$card->id.'/archive')->assertForbidden();

        $this->assertSame(1, Pipeline::count());
        $this->assertSame('Deal Baru', $card->fresh()->endorse);
    }

    public function test_tamu_tak_boleh_apa_pun(): void
    {
        $this->post('/pipelines', $this->payload())->assertRedirect('/login');
        $this->assertSame(0, Pipeline::count());
    }
}

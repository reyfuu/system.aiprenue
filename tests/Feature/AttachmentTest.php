<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\PipelineAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Lampiran kartu. Beda dari komentar: hanya manajemen (attachments.* masuk isManageRoute). */
class AttachmentTest extends TestCase
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

    public function test_unggah_lampiran_menyimpan_file_dan_metadata(): void
    {
        Storage::fake('public');
        $card = $this->card();
        $owner = $this->user();

        $this->actingAs($owner)->post("/pipelines/{$card->id}/attachments", [
            'file' => UploadedFile::fake()->create('kontrak.pdf', 12, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $a = PipelineAttachment::first();
        $this->assertSame('kontrak.pdf', $a->name);          // nama asli dipertahankan
        $this->assertSame($owner->id, $a->user_id);          // pengunggah = user login
        $this->assertSame($card->id, $a->pipeline_id);
        $this->assertSame(12 * 1024, $a->size);
        $this->assertStringStartsWith('attachments/', $a->path);
        Storage::disk('public')->assertExists($a->path);
    }

    public function test_file_wajib(): void
    {
        $card = $this->card();

        $this->actingAs($this->user())->post("/pipelines/{$card->id}/attachments", [])
            ->assertSessionHasErrors('file');
    }

    public function test_file_lebih_dari_10mb_ditolak(): void
    {
        Storage::fake('public');
        $card = $this->card();

        $this->actingAs($this->user())->post("/pipelines/{$card->id}/attachments", [
            'file' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, PipelineAttachment::count());
    }

    public function test_hapus_lampiran_ikut_membuang_file_fisik(): void
    {
        Storage::fake('public');
        $card = $this->card();
        $owner = $this->user();
        $this->actingAs($owner)->post("/pipelines/{$card->id}/attachments", [
            'file' => UploadedFile::fake()->create('kontrak.pdf', 5, 'application/pdf'),
        ]);
        $a = PipelineAttachment::first();

        $this->actingAs($owner)->delete('/attachments/'.$a->id)->assertSessionHasNoErrors();

        $this->assertSame(0, PipelineAttachment::count());
        Storage::disk('public')->assertMissing($a->path);   // jangan tinggalkan sampah di storage
    }

    public function test_staff_tak_boleh_unggah_atau_hapus_lampiran(): void
    {
        Storage::fake('public');
        $card = $this->card();
        $this->actingAs($this->user())->post("/pipelines/{$card->id}/attachments", [
            'file' => UploadedFile::fake()->create('kontrak.pdf', 5, 'application/pdf'),
        ]);
        $a = PipelineAttachment::first();
        $staff = $this->user('staff');

        $this->actingAs($staff)->post("/pipelines/{$card->id}/attachments", [
            'file' => UploadedFile::fake()->create('lain.pdf', 5, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($staff)->delete('/attachments/'.$a->id)->assertForbidden();

        $this->assertSame(1, PipelineAttachment::count());
    }
}

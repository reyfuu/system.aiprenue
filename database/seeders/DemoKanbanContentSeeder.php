<?php

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\Content;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Data demo untuk mencoba Kanban, Date Marker, Tracking, dan Content mingguan. */
class DemoKanbanContentSeeder extends Seeder
{
    public function run(): void
    {
        $board = 'todolist';
        $columns = BoardColumn::where('board_key', $board)->orderBy('position')->pluck('key')->values();
        $assignee = User::whereIn('role', ['staff', 'admin', 'manager'])->value('id');

        $cards = [
            ['Riset tren AI minggu ini', 0, -10, -2, ['Urgent']],
            ['Susun kalender konten Agustus', 0, -8, 5, []],
            ['Tulis script Reels edukasi', 1, -6, 2, []],
            ['Edit video behind the scene', 1, -5, -1, ['Urgent']],
            ['Review caption launching', 1, -3, 3, []],
            ['Jadwalkan posting Instagram', 2, -2, 1, []],
            ['Publikasi carousel bisnis', 2, -14, -7, []],
            ['Evaluasi performa konten', 0, 0, 7, []],
        ];

        foreach ($cards as [$title, $columnIndex, $createdOffset, $deadlineOffset, $labels]) {
            $progress = $columns[$columnIndex] ?? $columns->first() ?? 'todo';
            $card = Pipeline::firstOrCreate(
                ['category' => $board, 'endorse' => '[DEMO] '.$title],
                [
                    'progress' => $progress,
                    'description' => 'Data dummy untuk mencoba alur kerja Kanban dan halaman Tracking.',
                    'notes' => 'Silakan edit, pindahkan, atau hapus kartu demo ini.',
                    'account' => 'fk',
                    'payment_status' => 'belum',
                    'assigned_to' => $assignee,
                    'deadline' => today()->addDays($deadlineOffset),
                    'labels' => array_map(fn ($name) => ['name' => $name, 'color' => 'bg-red-500'], $labels),
                    'done' => $columnIndex === 2,
                ]
            );

            // Date Marker harus bervariasi supaya filter tanggal langsung terlihat.
            $card->timestamps = false;
            $card->forceFill(['created_at' => now()->addDays($createdOffset)])->saveQuietly();
        }

        $contents = [
            ['AI Preneur', 'Reels', 'Edukasi', 'editing', -10, 'Bisnis kamu sudah dibaca AI belum?'],
            ['Raveloux', 'Carousel', 'Promosi', 'review', -8, 'Tiga detail yang membuat outfit terlihat premium.'],
            ['Rave Tailor', 'Reels', 'Behind the Scene', 'script', -6, 'Dari kain menjadi jas dalam 30 detik.'],
            ['AI Preneur', 'YouTube Shorts', 'Edukasi', 'scheduled', -3, 'Kesalahan pertama saat memakai AI untuk bisnis.'],
            ['Raveloux', 'Story', 'Engagement', 'published', -1, 'Pilih look favorit kamu hari ini.'],
            ['Rave Tailor', 'Carousel', 'Testimoni', 'draft', 2, 'Cerita pelanggan sebelum dan sesudah fitting.'],
        ];

        foreach ($contents as $index => [$comp, $jenis, $kategori, $progress, $dayOffset, $hook]) {
            Content::firstOrCreate(
                ['referensi' => 'https://example.com/demo-content-'.($index + 1)],
                [
                    'comp' => $comp,
                    'jenis_postingan' => $jenis,
                    'kategori' => $kategori,
                    'inti_pesan' => 'Materi dummy untuk mencoba tabel dan filter Content.',
                    'hook_material' => $hook,
                    'brief_original' => "Buat content {$jenis} dengan pembuka yang kuat dan satu ajakan bertindak.",
                    'opsi_brief' => 'Gunakan bahasa ringan, visual bersih, dan durasi singkat.',
                    'script_remake' => "Hook: {$hook}\nIsi: jelaskan masalah, solusi, lalu CTA.",
                    'editor' => 'Demo Editor',
                    'progress' => $progress,
                    'tanggal_upload' => today()->addDays($dayOffset),
                    'link_hasil_editing' => 'https://example.com/editing-'.($index + 1),
                    'link_b_roll' => 'https://example.com/b-roll-'.($index + 1),
                    'caption' => 'Caption dummy content #aipreneur #content',
                    'link_ai_kata_kunci' => 'https://example.com/ai-keyword-'.($index + 1),
                ]
            );
        }
    }
}

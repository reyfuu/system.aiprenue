<?php

use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Label;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pemulihan board Kanban tim yang hilang di server.
 *
 * Dibuat sebagai MIGRASI, bukan seeder, supaya ikut terpasang lewat
 * `php artisan migrate` saat deploy — produksi tidak menjalankan seeder.
 *
 * Sumber datanya hanya 17 tangkapan layar board produksi
 * (app.aipreneur.co.id/pipelines/kanban?category=todo); databasenya sudah
 * tidak ada. Karena itu migrasi ini HANYA memuat yang benar-benar terbaca:
 *
 *   ADA        — nama kolom, judul kartu, penempatan & urutan kartu per kolom,
 *                dan label status yang chip-nya terbaca.
 *   TIDAK ADA  — tanggal dibuat, deadline, completed_at, PIC, deskripsi,
 *                komentar, dan lampiran. Teksnya terlalu kecil untuk dibaca
 *                tanpa menebak, dan menebak tanggal berarti mengarang riwayat
 *                pekerjaan tim.
 *
 * Akibatnya kartu hasil pemulihan tampil tanpa penanda tanggal/ketepatan dan
 * tanpa penugasan, sehingga statistik per orang di atas board menampilkan
 * semuanya sebagai "Belum ditugaskan". Kolom "DONE ..." tetap menandakan
 * pekerjaan selesai lewat penempatan kolom + label Selesai, bukan completed_at.
 */
return new class extends Migration
{
    /** Board tujuan — samakan dengan produksi (?category=todo). */
    private const BOARD_KEY = 'todo';

    private const BOARD_NAME = 'Todolist Tim';

    /**
     * Isi board per orang, urut kiri→kanan seperti di tangkapan layar.
     *
     * Bentuk tiap entri: kolom To Do dan kolom DONE milik satu orang, masing-
     * masing dengan daftar kartunya. Kartu = [judul, label...]; nama label
     * mengikuti tabel `labels` dan yang belum ada dibuat otomatis.
     */
    private function papan(): array
    {
        return [
            [
                'todo' => ['key' => 'gilang_todo', 'name' => 'To Do List Gilang', 'cards' => [
                    ['[O3] Governance, Priorites & Weekly Review OKR'],
                    ['Diferensiasi Kurikulum Kelas Berbayar VALUE LADDER'],
                    ['Automate Report Ads Rave', 'Penting'],
                ]],
                'done' => ['key' => 'gilang_done', 'name' => 'DONE GILANG', 'cards' => [
                    ['Riset Kompetitor Secara Menyeluruh Untuk Project Pengembangan Bootcamp AI Preneur', 'Penting', 'Selesai'],
                    ['Buat AI Agent (Karyawan AI Preneur)', 'Selesai', 'Penting'],
                    ['Buat Materi Claude (Kelas)', 'Selesai', 'Penting'],
                ]],
            ],
            [
                'todo' => ['key' => 'uli_todo', 'name' => 'To Do list Uli', 'cards' => [
                    ['SOP DAILY TARGET BULANAN'],
                    ['[O2] Build Endorsement & Sponsorship Pipeline'],
                    ['[O3] Build FK Audience Growth Engine'],
                    ['Sistem Automation Konten WA Channel 10-20 Output/Hari (sub agent, schedule task, Kanban, RAG/Cloud context engineering) DL 20 Jul'],
                    ['Training AI Sesuai Tools FK + Dokumentasi Job Instruction ChatGPT, Porting ke Cloud untuk RAG - DL 20 Jul'],
                    ['Bangun Agent & Sub Agent per Project (riset, copywriting, scripting, editing, desain, QA/self critic) + Prompt Library & SOP Onboarding - v0.1 DL 30 Jul, iterasi mingguan'],
                    ['Produksi Konten Pilar News / Update / Kelas Jualan untuk YouTube & IG-Reels-TikTok, target 20 konten per hari - Awal Agustus'],
                    ['Playbook Teknis (ChatGPT Work, Notion, Cloud, cloud PDF manajemen kuota, reset teks 1 Agu, limit upload 80/2-3 jam) - DL 1 Agu'],
                    ['Sesi dengan Atasan: Aktivasi WA Channel + Sinkronisasi Subscriber/Cloud - Awal Agustus setelah IG/YouTube stabil'],
                ]],
                'done' => ['key' => 'uli_done', 'name' => 'DONE ULI', 'cards' => [
                    ['Split 3 Project Terpisah: YouTube / IG-Reels-TikTok-Shorts / WA Channel - DL 30 Jul', 'Selesai'],
                    ['Yang cicilan ke 2 segera di bayarkan di GOdakal di reminder, jika gak ada balasan segera di hapus', 'Selesai', 'Tertunda'],
                    ['Buat kumpulan Hook Yang bisa membantu banyak proses konten FK', 'Selesai'],
                    ['Rangkum Dan Liat video ini dan praktekan', 'Selesai', 'Tertunda'],
                ]],
            ],
            [
                'todo' => ['key' => 'bram_todo', 'name' => 'To do list Bram', 'cards' => [
                    ['[O2] Editing Course & Program AI Preneur'],
                    ['[O3] Eksekusi YouTube Growth FK'],
                    ['Youtube Daily FK (AI Tools LLM)'],
                ]],
                'done' => ['key' => 'bram_done', 'name' => 'DONE BRAM', 'cards' => [
                    ['Youtube Hostinger', 'Selesai'],
                    ['Youtube Skywork', 'Selesai'],
                    ['Youtube Daily FK (Essential AI Skill 2026)', 'Selesai'],
                    ['Youtube Daily FK (Pakai AI dari Nol-Cuan)', 'Selesai'],
                ]],
            ],
            [
                'todo' => ['key' => 'atif_todo', 'name' => 'To Do List Atif', 'cards' => [
                    ['[O2] Short-Form Program AI Preneur'],
                    ['[O3] Eksekusi Short Form Growth FK'],
                    ['Reels Level AI'],
                    ['Reels 4 fundamental AI'],
                    ['Reels website claude'],
                    ['Reels Prabowo AI agent'],
                ]],
                'done' => ['key' => 'atif_done', 'name' => 'DONE AFIF', 'cards' => [
                    ['Reels News Indo kapan', 'Selesai', 'Tertunda'],
                    ['Reels 4 level marketing'],
                    ['Reels Webshare (endors)'],
                    ['Reels News AI Marketing'],
                    ['news pocet ai nyocol'],
                    ['News Amerika', 'Selesai'],
                    ['Reels Bisnis 2026'],
                ]],
            ],
            [
                'todo' => ['key' => 'icha_todo', 'name' => 'To Do List Icha', 'cards' => [
                    ['Poles PPT Company Profile FK terbaru buat project CHATGPT', 'Review', 'Urgent'],
                    ['[O3] FK Visual Growth System'],
                    ['[O2] Endorsement Media Kit & Sponsor Assets'],
                    ['[O2] Software House Sales Collateral'],
                    ['[O2] Visual Assets Education Products'],
                    ['[O1] Dashboard UI & Visual System Support'],
                ]],
                'done' => ['key' => 'icha_done', 'name' => 'DONE ICHA', 'cards' => [
                    ['Prompting 12 feed Raveloux', 'Urgent', 'Selesai'],
                    ['Prompting 12 feed Raveloux (batch 2)', 'Urgent', 'Selesai'],
                    ['Buat LYNKID dari kembali pulih alihkan ke website dan tiktok nya', 'Selesai'],
                    ['Remake PPT materi ke long ppt', 'Selesai'],
                    ['Upload 12 posting lagi di ig untuk kembali pulih', 'Selesai'],
                    ['Cover Youtube Skywork', 'Selesai'],
                ]],
            ],
            [
                'todo' => ['key' => 'shiva_todo', 'name' => 'To Do list Shiva', 'cards' => [
                    ['[O2] Riset Pricing & Value Ladder Support'],
                    ['[O2] Draft Assets & Dokumentasi Program'],
                    ['[O2] Curriculum Audit & Source Pack Academy'],
                    ['[O2] Riset Kompetisi Pendidikan AI'],
                    ['DAILY UPLOAD AIPRENEUR NEWS', 'Review'],
                ]],
                'done' => ['key' => 'shiva_done', 'name' => 'DONE SHIVA', 'cards' => [
                    ['BUAT S BACKGROUND YANG BAGUS DARI PROJECT OUTFIT SOCIETY', 'Selesai', 'Tertunda'],
                ]],
            ],
            [
                'todo' => ['key' => 'ilham_todo', 'name' => 'To Do List Ilham (IT ENGINEER)', 'cards' => [
                    ['Buat LMS AIPRENEUR SAMPAI JADI'],
                ]],
                // Terlihat kosong di tangkapan layar.
                'done' => ['key' => 'ilham_done', 'name' => 'Done Ilham', 'cards' => []],
            ],
            [
                'todo' => ['key' => 'audhi_todo', 'name' => 'To Do list Audhi', 'cards' => [
                    ['[O1] PRD, Arsitektur & Roadmap System AI Preneur'],
                    ['[O2] Launch Software House Target September 2026'],
                    ['[O1/KR1.6] Omnichannel Chat Insight'],
                    ['[O1/KR1.7] LMS Internal & Learning Insight'],
                    ['[O1/KR1.6] Pembukuan Perusahaan'],
                    ['[O1/KR1.4] Native Kanban & Activity Log'],
                    ['[O1/KR1.5] Invoice Maker & Sales Pipeline'],
                    ['[O1/KR1.3] Meta Ads Dashboard'],
                    ['[O1/KR1.2] Content Insight Dashboard'],
                    ['[O1/KR1.1] Auto-Publishing 4 Platform'],
                    ['BUAT DECK IN IUNTUK CUSTOMER BISA PAHAM DIA DAPAT APA AJA DARI SYSTEM YANG DI BUAT DARI PROD PILOT'],
                ]],
                'done' => ['key' => 'audhi_done', 'name' => 'DONE AUDHI', 'cards' => [
                    ['BUAT APP (Kayak Cekat AI) (PRD)', 'Selesai', 'Tertunda'],
                    ['Buat MCP Server untuk Sales Pipeline', 'Selesai'],
                    ['BUAT MCP SERVER UNTUK AIPRENEUR APP', 'Selesai', 'Tertunda'],
                    ['BUAT LMS AIPRENEUR PRIBADI (PRD)', 'Selesai'],
                ]],
            ],
            [
                'todo' => ['key' => 'christian_todo', 'name' => 'To Do List Christian', 'cards' => [
                    ['[O2] Value Ladder & Pricing B2C/B2B'],
                    ['[O2] Coaching & Workshop Offline'],
                    ['[O2] Update Academy & Re Recording'],
                    ['[O2] Marketing Bootcamp Live Cohort'],
                    ['[O2] Marketing Bootcamp Pre-Recorded'],
                    ['Buat Landing Page Bootcamp AI Filmmaking untuk Meta Ads', 'Penting'],
                    ['AIPRENEUR ACADEMY YANG HARUS DI PERSIAPKAN APA AJA YA'],
                    ['Riset Competitor Untuk yang bootcamp film making dari harga dan kurikulum mereka', 'Urgent'],
                ]],
                'done' => ['key' => 'christian_done', 'name' => 'DONE CHRISTIAN', 'cards' => [
                    ['CAPCUT ENDORSE SEEDANCE 2.0 REVISI', 'Selesai', 'Urgent'],
                    ['CAPCUT ENDORSE', 'Selesai', 'Urgent'],
                    ['PPT Kurikulum Mentahan', 'Selesai'],
                ]],
            ],
            [
                'todo' => ['key' => 'aisyah_todo', 'name' => 'TO DO LIST AISYAH', 'cards' => [
                    ['SCRIPT PER MINGGU', 'Process'],
                    ['RAVELOUX'],
                    ['RAVE TAILOR'],
                ]],
                'done' => ['key' => 'aisyah_done', 'name' => 'DONE AISYAH', 'cards' => [
                    ['TAKE KONTEN BU IPIN 28 JULI', 'Selesai'],
                    ['TAKE KONTEN IVE 24 JULI', 'Selesai'],
                ]],
            ],
            [
                'todo' => ['key' => 'fikri_todo', 'name' => 'TO DO LIST FIKRI', 'cards' => [
                    ['Daily reels RVT week 2 Agustus 2026', 'Process'],
                    ['Daily reels RVX week 2 Agustus 2026', 'Process'],
                ]],
                'done' => ['key' => 'fikri_done', 'name' => 'DONE FIKRI', 'cards' => [
                    ['Edit konten Ive', 'Selesai'],
                    ['Edit konten Bu Ipin', 'Selesai'],
                    ['Take konten Ive 24 Juli 2026', 'Selesai'],
                    ['Take konten Bu Ipin 28 Juli 2026', 'Selesai'],
                    ['Daily reels RVT week 1 Agustus 2026', 'Selesai'],
                    ['Daily reels RVX week 1 Agustus 2026', 'Selesai'],
                ]],
            ],
        ];
    }

    public function up(): void
    {
        // Lingkungan tes dilewati. Migrasi ini menyisipkan DATA, bukan mengubah
        // skema, dan RefreshDatabase menjalankan seluruh migrasi untuk tiap tes
        // — tanpa penjagaan ini 97 kartu ikut masuk ke database tes dan semua
        // tes yang mengasumsikan tabel pipelines kosong ikut gagal.
        if (app()->environment('testing')) {
            return;
        }

        $board = Category::firstOrCreate(
            ['key' => self::BOARD_KEY, 'type' => 'kanban'],
            ['name' => self::BOARD_NAME],
        );

        // Jangan pernah menimpa board yang sudah berisi kartu. Migrasi ini
        // memulihkan data yang hilang, bukan mengganti pekerjaan yang sedang
        // berjalan — mis. bila board sempat diisi ulang manual di server.
        if (Pipeline::where('category', self::BOARD_KEY)->exists()) {
            return;
        }

        // Pembuat kartu = owner pertama. Pada database yang benar-benar baru
        // (migrate:fresh) tabel users masih kosong karena seeder berjalan
        // setelah migrasi — created_by nullable, jadi cukup dibiarkan null.
        $pembuat = User::where('role', 'owner')->orderBy('id')->value('id');

        DB::transaction(function () use ($pembuat): void {
            $posisiKolom = 0;

            foreach ($this->papan() as $orang) {
                foreach ([$orang['todo'], $orang['done']] as $kolom) {
                    BoardColumn::updateOrCreate(
                        ['board_key' => self::BOARD_KEY, 'key' => $kolom['key']],
                        ['name' => $kolom['name'], 'position' => $posisiKolom++],
                    );

                    foreach (array_values($kolom['cards']) as $posisi => $kartu) {
                        $card = Pipeline::create([
                            'category' => self::BOARD_KEY,
                            'progress' => $kolom['key'],
                            'endorse' => $kartu[0],
                            'labels' => $this->labels(array_slice($kartu, 1)),
                            'account' => 'fk',
                            'payment_status' => 'belum',
                            'created_by' => $pembuat,
                        ]);

                        // `position` tidak ada di $fillable Pipeline, jadi harus
                        // diisi setelah create — lewat mass assignment nilainya
                        // dibuang diam-diam & semua kartu menumpuk di posisi 0.
                        $card->position = $posisi;
                        $card->save();
                    }
                }
            }
        });
    }

    /**
     * Rollback sengaja TIDAK menghapus seluruh isi board.
     *
     * Yang dihapus hanya kartu yang judulnya persis sama dengan hasil
     * pemulihan; kartu baru yang dibuat tim setelah migrasi ini — termasuk
     * kartu pulihan yang judulnya sudah diedit — tetap aman. Kolom dan board
     * hanya ikut dihapus bila setelah itu benar-benar tidak ada kartu tersisa.
     */
    public function down(): void
    {
        $judul = [];
        foreach ($this->papan() as $orang) {
            foreach ([$orang['todo'], $orang['done']] as $kolom) {
                foreach ($kolom['cards'] as $kartu) {
                    $judul[] = $kartu[0];
                }
            }
        }

        DB::transaction(function () use ($judul): void {
            // forceDelete(), BUKAN delete(): Pipeline memakai SoftDeletes, jadi
            // delete() hanya mengisi deleted_at. Barisnya akan tertinggal dan
            // migrate berikutnya — yang hanya menghitung kartu hidup — menyisipkan
            // 97 kartu lagi sehingga isinya berlipat tiap siklus rollback.
            Pipeline::where('category', self::BOARD_KEY)
                ->whereIn('endorse', $judul)
                ->forceDelete();

            if (Pipeline::where('category', self::BOARD_KEY)->exists()) {
                return;
            }

            BoardColumn::where('board_key', self::BOARD_KEY)->delete();
            Category::where('key', self::BOARD_KEY)->where('type', 'kanban')->delete();
        });
    }

    /**
     * Ubah daftar nama label jadi snapshot {name, group, color} seperti yang
     * disimpan kolom `pipelines.labels`. Label yang belum ada dibuat dulu —
     * "Tertunda" misalnya hanya ada di produksi.
     *
     * @param  array<int, string>  $nama
     * @return array<int, array<string, mixed>>
     */
    private function labels(array $nama): array
    {
        // group 1 = status, group 2 = penanda prioritas/keadaan.
        // bg-rose-500 sudah ada di safelist resources/css/app.css, jadi chip-nya
        // tetap berwarna walau warnanya datang dari database.
        $bawaan = ['Tertunda' => ['group' => 2, 'color' => 'bg-rose-500']];

        return collect($nama)
            ->map(function (string $n) use ($bawaan) {
                $label = Label::firstOrCreate(
                    ['name' => $n],
                    $bawaan[$n] ?? ['group' => 2, 'color' => 'bg-slate-500'],
                );

                return ['name' => $label->name, 'group' => $label->group, 'color' => $label->color];
            })
            ->all();
    }
};

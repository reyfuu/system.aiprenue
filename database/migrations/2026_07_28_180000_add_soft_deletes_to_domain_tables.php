<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tambah `deleted_at` (SoftDeletes) ke seluruh tabel domain.
 *
 *  Alasannya: penghapusan tabel-tabel ini (kategori board, KR/Objective OKR,
 *  transaksi keuangan, dst) selama ini permanen — sekali hapus, data hilang
 *  tanpa jejak, termasuk dari AuditLog yang cuma mencatat AKSInya, bukan
 *  ISI baris yang lenyap. SoftDeletes membuatnya bisa dipulihkan.
 *
 *  Kolom nullable dan ditambah lewat `softDeletes()` biasa (bukan raw SQL
 *  spesifik dialek) supaya jalan sama persis di SQLite (dev lama), MariaDB
 *  (dev) & MySQL (server shared hosting) — lihat AGENTS.md soal itu.
 *
 *  Setiap tabel dijaga `Schema::hasColumn` dulu: idempoten, aman dijalankan
 *  ulang atau di server yang sebagian kolomnya sudah ada.
 *
 *  `pipelines` SENGAJA dilewati — modelnya sudah pakai SoftDeletes sejak
 *  migrasi lain, jadi kolomnya sudah ada. `audit_logs` juga dilewati:
 *  ledger append-only, tak boleh soft delete (lihat AuditLog::$timestamps).
 *
 *  TIMESTAMP sengaja 2026_07_28_180000 (bukan tanggal hari ini) — ditaruh
 *  tepat setelah `pipeline_tasks` (tabel terakhir di daftar ini yang
 *  dibuat) dan SEBELUM migrasi data 2026_07_30_150000/…_160000 yang
 *  memakai model BoardColumn/Category/Label/User langsung. Model-model itu
 *  kena SoftDeletes juga (lihat app/Models/*), jadi query mereka otomatis
 *  menambahkan `WHERE deleted_at IS NULL` — kalau migrasi ini taruh
 *  belakangan, `migrate:fresh` pecah karena kolomnya belum ada saat
 *  migrasi data itu jalan. `okr_periods` — satu-satunya tabel di daftar
 *  yang dibuat SESUDAH titik ini (2026_07_31_000000) — dapat `deleted_at`
 *  langsung di migrasi create-nya, bukan lewat migrasi ini; guard
 *  `hasColumn` di bawah otomatis melewatinya di sini.
 */
return new class extends Migration
{
    /** Tabel domain yang perlu deleted_at. Pivot (order_output,
     *  output_pipeline) dan tabel framework (sessions/cache/jobs/dll)
     *  sengaja tak diikutkan — bukan model Eloquent yang di-soft-delete. */
    private array $tabel = [
        'absences', 'board_columns', 'board_quarter_targets', 'categories', 'contents',
        'insight_accounts', 'insight_contents', 'inventories', 'key_results', 'labels',
        'mindmaps', 'objectives', 'okr_periods', 'orders', 'outputs',
        'pipeline_attachments', 'pipeline_comments', 'pipeline_tasks', 'scripts',
        'transactions', 'users',
    ];

    public function up(): void
    {
        foreach ($this->tabel as $nama) {
            if (! Schema::hasTable($nama) || Schema::hasColumn($nama, 'deleted_at')) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabel as $nama) {
            if (! Schema::hasTable($nama) || ! Schema::hasColumn($nama, 'deleted_at')) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};

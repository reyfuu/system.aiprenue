-- Setara migrasi Laravel: database/migrations/2026_07_28_180000_add_soft_deletes_to_domain_tables.php
-- Tambah kolom `deleted_at` (SoftDeletes) ke tabel domain yang belum punya.
--
-- Jalankan manual (server tak menjalankan `artisan migrate`, lihat AGENTS.md).
-- Setiap ALTER TABLE aman diulang: kalau kolomnya sudah ada, MariaDB/MySQL akan
-- menolak dengan error "Duplicate column name" — lewati baris itu saja dan
-- lanjutkan ke baris berikutnya, jangan hentikan seluruh skrip.
--
-- TIDAK termasuk `pipelines` (sudah punya `deleted_at` dari migrasi lama) dan
-- `audit_logs` (ledger append-only, sengaja tak boleh soft delete).
--
-- `okr_periods` TIDAK ada di sini: tabel itu sendiri belum pernah dibuat di
-- server (migrasi 2026_07_31_000000_create_okr_periods_table.php belum
-- dideploy). Saat migrasi itu dijalankan di server, kolom `deleted_at`-nya
-- sudah termasuk di CREATE TABLE-nya — tak perlu ALTER terpisah.

ALTER TABLE `absences` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `board_columns` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `board_quarter_targets` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `categories` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `contents` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `insight_accounts` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `insight_contents` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `inventories` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `key_results` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `labels` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `mindmaps` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `objectives` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `orders` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `outputs` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `pipeline_attachments` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `pipeline_comments` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `pipeline_tasks` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `scripts` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `transactions` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;

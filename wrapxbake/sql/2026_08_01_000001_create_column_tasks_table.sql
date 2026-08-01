-- Deploy: checklist delegasi per-kolom (menggantikan routine_tasks personal v1).
-- Import via phpMyAdmin di DB server. Jalankan SEBELUM upload build baru,
-- kalau tidak halaman board Kanban error (controller query column_tasks).

DROP TABLE IF EXISTS `routine_tasks`;

CREATE TABLE `column_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `board_column_id` bigint unsigned NOT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `position` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `column_tasks_board_column_id_position_index` (`board_column_id`,`position`),
  KEY `column_tasks_assigned_to_foreign` (`assigned_to`),
  KEY `column_tasks_created_by_foreign` (`created_by`),
  CONSTRAINT `column_tasks_board_column_id_foreign` FOREIGN KEY (`board_column_id`) REFERENCES `board_columns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `column_tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `column_tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sinkronkan tabel migrations Laravel (biar `php artisan migrate` tak dobel).
DELETE FROM `migrations` WHERE `migration` = '2026_08_01_000000_create_routine_tasks_table';
SET @b = (SELECT COALESCE(MAX(`batch`),0)+1 FROM `migrations`);
INSERT INTO `migrations` (`migration`,`batch`) VALUES ('2026_08_01_000001_create_column_tasks_table', @b);

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Checkpoint database: dump logis terkompresi + rotasi.
 *
 * Bagian "Checkpoint" dari strategi recovery. Dijalankan cron di shared hosting
 * (Hostinger tak punya scheduler persisten), mis. tiap jam:
 *   /opt/alt/php84/usr/bin/php /path/ke/app/artisan db:checkpoint --keep=48
 *
 * PITR penuh (replay binlog) TIDAK tersedia di shared hosting — akses binlog
 * tak diberikan. Granularitas pemulihan = interval checkpoint; pemulihan antar
 * checkpoint dilakukan per-baris dari audit_logs (old_values/new_values).
 */
class DbCheckpoint extends Command
{
    protected $signature = 'db:checkpoint {--keep=48 : Jumlah checkpoint terbaru yang disimpan (sisanya dihapus)}';

    protected $description = 'Buat checkpoint (dump .sql.gz) database lalu rotasi yang lama. Untuk cron backup.';

    public function handle(): int
    {
        $conn = config('database.default');
        $db = config("database.connections.$conn");

        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('db:checkpoint hanya untuk MySQL/MariaDB (driver aktif: '.($db['driver'] ?? 'null').').');

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            $this->error("Tak bisa membuat folder backup: $dir");

            return self::FAILURE;
        }

        $stamp = now()->format('Ymd-His');
        $file = "$dir/checkpoint-$stamp.sql.gz";

        // mysqldump → gzip. --single-transaction: konsisten tanpa mengunci (InnoDB).
        // Password lewat env MYSQL_PWD supaya tak tampil di daftar proses (`ps`).
        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --quick --routines --skip-lock-tables %s | gzip > %s',
            escapeshellarg((string) ($db['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($db['port'] ?? 3306)),
            escapeshellarg((string) $db['username']),
            escapeshellarg((string) $db['database']),
            escapeshellarg($file)
        );

        $proc = Process::fromShellCommandline($cmd, base_path(), ['MYSQL_PWD' => (string) ($db['password'] ?? '')], null, 600);
        $proc->run();

        // Gagal / file kosong / terlalu kecil (dump error hanya berisi header) → buang.
        if (! $proc->isSuccessful() || ! is_file($file) || filesize($file) < 100) {
            @unlink($file);
            $this->error('Checkpoint GAGAL: '.trim($proc->getErrorOutput() ?: $proc->getOutput()));

            return self::FAILURE;
        }

        $this->info(sprintf('Checkpoint OK: %s (%s KB)', basename($file), number_format(filesize($file) / 1024)));

        // Rotasi: nama file = timestamp, jadi urut string = urut waktu. Simpan N terbaru.
        $keep = max(1, (int) $this->option('keep'));
        $all = glob("$dir/checkpoint-*.sql.gz") ?: [];
        rsort($all);
        foreach (array_slice($all, $keep) as $old) {
            @unlink($old);
            $this->line('  rotasi — hapus: '.basename($old));
        }

        return self::SUCCESS;
    }
}

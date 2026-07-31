<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Restore database dari checkpoint .sql.gz.
 *
 * DESTRUKTIF: menimpa isi database aktif. Wajib konfirmasi (ketik nama DB)
 * kecuali --force. Pasangan dari db:checkpoint dalam strategi recovery.
 *
 * Untuk pemulihan point-in-time yang lebih halus: restore checkpoint terdekat
 * SEBELUM insiden, lalu terapkan/kembalikan mutasi individual dari audit_logs.
 */
class DbRestore extends Command
{
    protected $signature = 'db:restore {file? : Nama/path checkpoint .sql.gz (kosong = paling baru)} {--force : Lewati konfirmasi}';

    protected $description = 'Restore database dari checkpoint (.sql.gz). DESTRUKTIF — menimpa data aktif.';

    public function handle(): int
    {
        $conn = config('database.default');
        $db = config("database.connections.$conn");

        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('db:restore hanya untuk MySQL/MariaDB.');

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        $file = $this->resolveFile($dir, $this->argument('file'));
        if ($file === null) {
            $this->error('Checkpoint tidak ditemukan.');

            return self::FAILURE;
        }

        $this->warn('Akan menimpa database "'.$db['database'].'" dengan:');
        $this->line('  '.$file.' ('.number_format(filesize($file) / 1024).' KB)');

        if (! $this->option('force')) {
            $jawab = $this->ask('Ketik nama database untuk konfirmasi (atau kosong untuk batal)');
            if ($jawab !== $db['database']) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        // gunzip → mysql. Password lewat MYSQL_PWD (tak tampil di `ps`).
        $cmd = sprintf(
            'gunzip -c %s | mysql --host=%s --port=%s --user=%s %s',
            escapeshellarg($file),
            escapeshellarg((string) ($db['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($db['port'] ?? 3306)),
            escapeshellarg((string) $db['username']),
            escapeshellarg((string) $db['database'])
        );

        $proc = Process::fromShellCommandline($cmd, base_path(), ['MYSQL_PWD' => (string) ($db['password'] ?? '')], null, 600);
        $proc->run();

        if (! $proc->isSuccessful()) {
            $this->error('Restore GAGAL: '.trim($proc->getErrorOutput() ?: $proc->getOutput()));

            return self::FAILURE;
        }

        $this->info('Restore selesai dari '.basename($file).'.');

        return self::SUCCESS;
    }

    /** Resolusi argumen file: nama di folder backup, path absolut, atau (kosong) checkpoint terbaru. */
    private function resolveFile(string $dir, ?string $arg): ?string
    {
        if ($arg === null || $arg === '') {
            $all = glob("$dir/checkpoint-*.sql.gz") ?: [];
            rsort($all);

            return $all[0] ?? null;
        }
        foreach ([$arg, "$dir/$arg"] as $cand) {
            if (is_file($cand)) {
                return realpath($cand);
            }
        }

        return null;
    }
}

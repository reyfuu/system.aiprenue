<?php

namespace App\Console\Commands;

use App\Support\Hermes;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Jalankan laporan harian Hermes secara manual atau via cron.
 *
 * Cron manual di shared hosting (Hostinger contoh):
 *   /opt/alt/php84/usr/bin/php /path/ke/app/artisan hermes:daily-report
 */
class HermesDailyReport extends Command
{
    protected $signature = 'hermes:daily-report
                            {--date= : Tanggal (Y-m-d), default hari ini}
                            {--send : Kirim notifikasi server. Default: kirim. (flag ini saat ini tetap diterima agar kompatibel) }';

    protected $description = 'Kirim ringkasan operasional harian via notifikasi server (Hermes).';

    public function handle(): int
    {
        $dateInput = $this->option('date');
        try {
            $date = $dateInput ? Carbon::parse($dateInput) : today();
        } catch (\Throwable) {
            $this->error('Format --date harus Y-m-d.');

            return self::FAILURE;
        }

        $summary = Hermes::buildDailySummary($date);
        $message = Hermes::formatMessage($summary, $date->translatedFormat('d M Y'));
        $title = "Hermes Daily Report — {$date->translatedFormat('d F Y')}";

        $this->info($title);
        $this->line($message);

        Hermes::sendToConfiguredRoles($date);
        $this->info('Notifikasi Hermes harian sudah dikirim.');

        return self::SUCCESS;
    }
}

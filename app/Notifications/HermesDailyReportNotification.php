<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi harian untuk ringkasan operasional Hermes.
 *
 * Tidak menggunakan queue supaya kiriman selesai di request yang sama.
 */
class HermesDailyReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly array $summary,
        private readonly string $reportDate,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'hermes_daily_report',
            'title' => $this->title,
            'message' => $this->message,
            'summary' => $this->summary,
            'report_date' => $this->reportDate,
            'url' => '/daily-report?from='.$this->reportDate.'&to='.$this->reportDate,
            'priority' => ['name' => 'Informasi'],
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi OKR yang tersimpan sebagai notifikasi database.
 *
 * Dipakai untuk semua kabar OKR: penugasan baru (kind=okr_assignment),
 * perubahan penugasan/target/deadline (okr_perubahan), laporan kartu
 * selesai (okr_selesai), dan pengingat deadline (okr_deadline).
 *
 * Tidak memakai ShouldQueue: insert notifikasi harus selesai dalam request yang
 * sama agar deployment server tidak bergantung pada worker antrean.
 */
class OkrAssignmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $url,
        private readonly int $objectiveId,
        private readonly ?int $keyResultId,
        private readonly ?array $priority = null,
        // Jenis kabar — dipakai UI/dedup untuk membedakan penugasan, perubahan,
        // laporan selesai, dan pengingat deadline.
        private readonly string $kind = 'okr_assignment',
        // Kartu terkait, bila ada. Wajib untuk dedup pengingat deadline
        // (satu kartu maksimal satu pengingat per hari).
        private readonly ?int $pipelineId = null,
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
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'objective_id' => $this->objectiveId,
            'key_result_id' => $this->keyResultId,
            'pipeline_id' => $this->pipelineId,
            'priority' => $this->priority,
        ];
    }
}

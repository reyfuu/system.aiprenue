<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Penugasan dari OKR yang tersimpan sebagai notifikasi database.
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
            'kind' => 'okr_assignment',
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'objective_id' => $this->objectiveId,
            'key_result_id' => $this->keyResultId,
            'priority' => $this->priority,
        ];
    }
}

<?php

namespace App\Support;

use App\Models\Pipeline;
use App\Models\User;
use App\Notifications\OkrAssignmentNotification;

/**
 * Satu tempat untuk semua aturan notifikasi OKR.
 *
 * Controller membangun isi notifikasinya (judul/pesan yang spesifik per
 * kejadian), sedangkan kelas ini memegang aturan yang dipakai ulang:
 * siapa yang boleh dilewati, bagaimana melaporkan kartu selesai ke pemilik
 * OKR, dan bagaimana pengingat deadline harian dibuat tanpa cron/queue.
 */
class OkrNotifications
{
    /**
     * Kirim notifikasi ke satu penerima.
     *
     * Dua kondisi pelewat: penerima tak ada (mis. PIC dikosongkan), dan
     * penerima adalah pelakunya sendiri — orang tak perlu diberi tahu soal
     * keputusan yang baru saja ia ambil sendiri.
     */
    public static function kirim(?User $penerima, ?int $pelakuId, OkrAssignmentNotification $notifikasi): void
    {
        if (! $penerima || ($pelakuId !== null && (int) $penerima->id === (int) $pelakuId)) {
            return;
        }

        $penerima->notify($notifikasi);
    }

    /**
     * Laporkan ke pemilik OKR bahwa kartu tertaut KR baru saja selesai.
     *
     * Dipanggil HANYA saat completed_at berpindah dari null ke terisi —
     * deteksi transisinya ada di pemanggil (drag, tombol selesai, sync tugas)
     * karena merekalah yang tahu nilai sebelumnya.
     *
     * Penerima = penanggung jawab KR + pembuat KR, dikurangi pelaku. Tautan
     * ke /okr hanya disertakan untuk peran yang memang boleh membukanya;
     * staff menerima isi kabarnya saja supaya tak mengeklik jalan buntu 403.
     */
    public static function laporkanKartuSelesai(Pipeline $kartu, ?User $pelaku): void
    {
        $kr = $kartu->keyResult;
        if (! $kr) {
            return;
        }

        $penerimaIds = collect([$kr->owner_id, $kr->created_by])
            ->filter()
            ->unique()
            ->reject(fn ($id) => $pelaku !== null && (int) $id === (int) $pelaku->id)
            ->values();

        foreach (User::whereIn('id', $penerimaIds)->get() as $user) {
            $user->notify(new OkrAssignmentNotification(
                title: 'Pekerjaan OKR selesai',
                message: sprintf(
                    '“%s” selesai dikerjakan%s — KR “%s”.',
                    $kartu->endorse,
                    $pelaku ? ' oleh '.$pelaku->name : '',
                    $kr->title,
                ),
                url: $user->canSee('okr') ? route('okr.index') : null,
                objectiveId: $kr->objective_id,
                keyResultId: $kr->id,
                priority: $kr->priority,
                kind: 'okr_selesai',
                pipelineId: $kartu->id,
            ));
        }
    }

    /**
     * Beri tahu pengguna bahwa ia ditugaskan ke kartu Kanban.
     *
     * Dipanggil saat kartu baru dibuat dengan penanggung jawab, atau saat
     * penanggung jawab kartu diubah ke orang lain. Kartu yang SUDAH selesai
     * (done=1) dilewat — tidak ada gunanya memberi tahu pekerjaan yang sudah
     * rampung.
     */
    public static function notifikasiPenugasanKartu(Pipeline $kartu, User $penerima, ?User $pelaku): void
    {
        if ($kartu->done) {
            return;
        }

        if ($pelaku && (int) $penerima->id === (int) $pelaku->id) {
            return;
        }

        $board = $kartu->category;
        $url = route('pipelines.kanban', ['category' => $board, 'card' => $kartu->id]);

        $penerima->notify(new OkrAssignmentNotification(
            title: 'Kartu Kanban ditugaskan ke Anda',
            message: sprintf(
                '“%s” — board %s%s.',
                $kartu->endorse,
                $board,
                $pelaku ? ' (dari '.$pelaku->name.')' : '',
            ),
            url: $url,
            objectiveId: 0,
            keyResultId: null,
            kind: 'kanban_assignment',
            pipelineId: $kartu->id,
        ));
    }

    /**
     * Pengingat deadline harian untuk kartu OKR yang ditugaskan ke user.
     *
     * Dibuat MALAS saat user membuka halaman (dipicu dari
     * HandleInertiaRequests), bukan lewat cron: shared hosting produksi tak
     * menjalankan scheduler. Dedup-nya satu kartu maksimal satu pengingat
     * per hari, jadi kunjungan berulang tak menumpuk notifikasi.
     *
     * Hanya kartu tertaut KR (key_result_id terisi) — reminder kartu biasa
     * sudah ditangani workReminders yang dihitung langsung.
     */
    public static function ingatkanDeadline(User $user): void
    {
        $kartu = Pipeline::where('assigned_to', $user->id)
            ->whereNotNull('key_result_id')
            ->whereNull('archived_at')
            ->whereNull('completed_at')
            ->where('done', false)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<=', today()->addDays(3))
            // objective_id ikut dimuat sekalian — notifikasi menaruhnya di
            // payload, dan tanpa with() tiap kartu menembak query sendiri.
            ->with('keyResult:id,objective_id')
            ->orderBy('deadline')
            ->limit(10)
            ->get(['id', 'category', 'endorse', 'deadline', 'key_result_id']);

        if ($kartu->isEmpty()) {
            return;
        }

        // Dedup disaring di PHP (bukan JSON where) supaya perilakunya sama
        // di SQLite maupun MySQL: id kartu yang sudah diingatkan hari ini.
        $terkirim = $user->notifications()
            ->whereDate('created_at', today())
            ->get(['data'])
            ->filter(fn ($n) => ($n->data['kind'] ?? null) === 'okr_deadline')
            ->map(fn ($n) => $n->data['pipeline_id'] ?? null)
            ->filter();

        foreach ($kartu as $card) {
            if ($terkirim->contains($card->id)) {
                continue;
            }

            // Negatif = sudah terlewat (diffInDays dengan absolute=false).
            $selisih = (int) today()->diffInDays($card->deadline, false);
            $waktu = $selisih > 0
                ? "tinggal {$selisih} hari"
                : ($selisih < 0 ? 'terlewat '.abs($selisih).' hari' : 'jatuh tempo hari ini');

            $user->notify(new OkrAssignmentNotification(
                title: 'Pengingat deadline OKR',
                message: sprintf(
                    '“%s” %s (deadline %s).',
                    $card->endorse,
                    $waktu,
                    $card->deadline->translatedFormat('d M Y'),
                ),
                url: route('pipelines.kanban', [
                    'category' => $card->category,
                    'card' => $card->id,
                ]),
                objectiveId: $card->keyResult?->objective_id ?? 0,
                keyResultId: $card->key_result_id,
                kind: 'okr_deadline',
                pipelineId: $card->id,
            ));
        }
    }
}

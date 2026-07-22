<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoardColumn;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Http\Request;

/** Pintu masuk "buat tugas dari luar" (MCP/Custom GPT/Gemini) → kartu Kanban.
 *  Bukan route web: tak ada sesi/CSRF & tak lewat EnsureMenuAccess — gerbangnya
 *  bearer token (TASK_AGENT_TOKEN), sama pola dgn /api/scripts & /api/insights. */
class TaskIngestController extends Controller
{
    /** Daftar board Kanban + kolomnya + user — supaya AI tahu pilihan yang sah. */
    public function boards(Request $request)
    {
        $this->pastikanTokenSah($request);

        $boards = [];
        foreach (Pipeline::categories('kanban') as $key => $name) {
            $boards[] = [
                'board' => $key,
                'name' => $name,
                'columns' => BoardColumn::where('board_key', $key)->orderBy('position')->pluck('name', 'key'),
            ];
        }

        return response()->json([
            'boards' => $boards,
            'users' => User::orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
        ]);
    }

    /** Buat satu kartu Kanban. Board/kolom yang tak valid jatuh ke default aman
     *  (board kanban pertama, kolom pertama) supaya AI tak perlu tahu key persis. */
    public function store(Request $request)
    {
        $this->pastikanTokenSah($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'board' => ['nullable', 'string', 'max:100'],
            'column' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'assignee' => ['nullable', 'string', 'max:255'],
        ]);

        $kanbanBoards = Pipeline::categories('kanban');       // key => nama
        abort_if($kanbanBoards === [], 422, 'Belum ada board Kanban.');

        // Board: pakai yang diminta bila valid, kalau tidak → board kanban pertama.
        $board = ($data['board'] ?? null);
        if (! $board || ! array_key_exists($board, $kanbanBoards)) {
            $board = array_key_first($kanbanBoards);
        }

        // Kolom: valid untuk board itu, kalau tidak → kolom 'todo' bila ada
        // (tugas baru wajarnya masuk antrean), selain itu kolom pertama.
        $cols = BoardColumn::where('board_key', $board)->orderBy('position')->pluck('key')->all();
        $column = ($data['column'] ?? null);
        if (! $column || ! in_array($column, $cols, true)) {
            $column = in_array('todo', $cols, true) ? 'todo' : ($cols[0] ?? 'todo');
        }

        // Assignee opsional: cocokkan email persis → nama persis → nama mengandung
        // (biar "audi" ketemu "Audi IT"). Ambil kecocokan pertama.
        $assignedTo = null;
        if (! empty($data['assignee'])) {
            $q = mb_strtolower(trim($data['assignee']));
            $assignedTo = User::where('email', $data['assignee'])
                ->orWhereRaw('LOWER(name) = ?', [$q])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%'.$q.'%'])
                ->value('id');
        }

        // endorse = judul kartu (yang tampil di Kanban); account/payment default kanban.
        $card = Pipeline::create([
            'category' => $board,
            'progress' => $column,
            'endorse' => $data['title'],
            'description' => $data['description'] ?? null,
            'account' => 'fk',
            'payment_status' => 'belum',
            'assigned_to' => $assignedTo,
            'done' => false,
        ]);

        return response()->json([
            'ok' => true,
            'id' => $card->id,
            'board' => $board,
            'column' => $column,
            'title' => $card->endorse,
            'assigned_to' => $assignedTo,
        ], 201);
    }

    private function pastikanTokenSah(Request $request): void
    {
        $token = (string) config('services.task_agent.token');

        abort_if($token === '', 503,
            'TASK_AGENT_TOKEN belum diisi di .env, lalu jalankan: php artisan optimize:clear');

        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Token tidak sah.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\PipelineTask;
use App\Support\OkrNotifications;
use Illuminate\Http\Request;

class PipelineTaskController extends Controller
{
    public function store(Request $request, Pipeline $pipeline)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'deadline' => 'nullable|date',
        ]);
        $pipeline->tasks()->create($data + ['position' => $pipeline->tasks()->max('position') + 1]);
        $this->sync($pipeline);
        return back()->with('status', 'Tugas ditambahkan.');
    }

    public function update(Request $request, PipelineTask $task)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'deadline' => 'nullable|date',
            'done' => 'sometimes|boolean',
        ]);
        if (array_key_exists('done', $data)) {
            $data['completed_at'] = $data['done'] ? ($task->completed_at ?? now()) : null;
            unset($data['done']);
        }
        $task->update($data);
        $this->sync($task->pipeline);
        return back()->with('status', 'Tugas diperbarui.');
    }

    public function destroy(PipelineTask $task)
    {
        $pipeline = $task->pipeline;
        $task->delete();
        $this->sync($pipeline);
        return back()->with('status', 'Tugas dihapus.');
    }

    private function sync(Pipeline $pipeline): void
    {
        $total = $pipeline->tasks()->count();
        $selesai = $pipeline->tasks()->whereNotNull('completed_at')->count();
        $done = $total > 0 && $selesai === $total;
        // Transisi dinilai dari nilai SEBELUM update: kartu yang memang baru
        // rampung karena tugas terakhirnya dicentang — bukan kartu yang sudah
        // lama selesai lalu tugasnya diutak-atik.
        $baruSelesai = $done && $pipeline->completed_at === null;
        $pipeline->update(['done' => $done, 'completed_at' => $done ? ($pipeline->completed_at ?? now()) : null]);

        if ($baruSelesai && $pipeline->key_result_id) {
            OkrNotifications::laporkanKartuSelesai($pipeline, request()->user());
        }
    }
}

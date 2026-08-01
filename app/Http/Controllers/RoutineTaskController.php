<?php

namespace App\Http\Controllers;

use App\Models\RoutineTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Checklist rutinitas harian — MILIK PRIBADI tiap user. Tak ada konsep
 *  "kelola punya orang lain": setiap aksi dipagari ke baris milik sendiri. */
class RoutineTaskController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:120']);

        RoutineTask::create([
            'user_id'  => $request->user()->id,
            'title'    => trim($data['title']),
            'position' => (int) RoutineTask::where('user_id', $request->user()->id)->max('position') + 1,
        ]);

        return back();
    }

    public function update(Request $request, RoutineTask $routineTask)
    {
        $this->authorizeOwner($request, $routineTask);
        $data = $request->validate(['title' => 'required|string|max:120']);
        $routineTask->update(['title' => trim($data['title'])]);

        return back();
    }

    /** Centang/lepas untuk HARI INI. completed_on = hari ini → tercentang;
     *  null → belum. Besok tanggalnya beda sehingga reset dengan sendirinya. */
    public function toggle(Request $request, RoutineTask $routineTask)
    {
        $this->authorizeOwner($request, $routineTask);
        $done = $routineTask->completed_on && $routineTask->completed_on->isToday();
        $routineTask->update(['completed_on' => $done ? null : today()]);

        return back();
    }

    public function destroy(Request $request, RoutineTask $routineTask)
    {
        $this->authorizeOwner($request, $routineTask);
        $routineTask->delete();

        return back();
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        // Hanya baris milik sendiri yang ditata ulang — id orang lain diabaikan.
        $owned = RoutineTask::where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])->pluck('id')->all();

        DB::transaction(function () use ($data, $owned) {
            foreach ($data['ids'] as $i => $id) {
                if (in_array($id, $owned, true)) {
                    RoutineTask::where('id', $id)->update(['position' => $i]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    private function authorizeOwner(Request $request, RoutineTask $task): void
    {
        abort_if($task->user_id !== $request->user()->id, 403);
    }
}

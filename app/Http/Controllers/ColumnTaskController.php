<?php

namespace App\Http\Controllers;

use App\Models\ColumnTask;
use Illuminate\Http\Request;

/** Checklist delegasi per kolom Kanban.
 *  - Buat/hapus/delegasi: owner/manager (canManage).
 *  - Centang selesai: staff yang ditugasi ATAU owner/manager. */
class ColumnTaskController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->canManage(), 403);

        $data = $request->validate([
            'board_column_id' => 'required|integer|exists:board_columns,id',
            'title'           => 'required|string|max:120',
            'assigned_to'     => 'required|integer|exists:users,id',
        ]);

        ColumnTask::create([
            'board_column_id' => $data['board_column_id'],
            'assigned_to'     => $data['assigned_to'],
            'created_by'      => $request->user()->id,
            'title'           => trim($data['title']),
            'position'        => (int) ColumnTask::where('board_column_id', $data['board_column_id'])->max('position') + 1,
        ]);

        return back();
    }

    /** Reset harian: toggle "selesai HARI INI". completed_on = hari ini → tercentang;
     *  null → belum. Lewat jam 12 malam tanggalnya beda sehingga reset sendiri. */
    public function toggle(Request $request, ColumnTask $columnTask)
    {
        abort_unless(
            $columnTask->assigned_to === $request->user()->id || $request->user()->canManage(),
            403
        );

        $done = $columnTask->isDoneToday();
        $field = ColumnTask::completedField();
        $value = $done ? null : ($field === 'completed_on' ? today() : now());

        $columnTask->update([$field => $value]);

        return back();
    }

    public function destroy(Request $request, ColumnTask $columnTask)
    {
        abort_unless($request->user()->canManage(), 403);
        $columnTask->delete();

        return back();
    }
}

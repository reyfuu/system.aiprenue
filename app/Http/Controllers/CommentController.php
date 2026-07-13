<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\PipelineComment;
use Illuminate\Http\Request;

// Komentar kartu — boleh untuk semua yg bisa lihat kanban (termasuk staff yg ditugasi).
class CommentController extends Controller
{
    // Tambah komentar pada kartu
    public function store(Request $request, Pipeline $pipeline)
    {
        $data = $request->validate([
            'body' => 'required|string|max:2000',            // isi komentar wajib
        ]);

        $pipeline->comments()->create([
            'user_id' => $request->user()->id,               // penulis = user login
            'body'    => $data['body'],                       // isi
        ]);

        return redirect()->back()->with('status', 'Komentar ditambahkan.');
    }

    // Hapus komentar — hanya penulis atau super admin/IT
    public function destroy(Request $request, PipelineComment $comment)
    {
        $user = $request->user();
        if ($comment->user_id !== $user->id && ! $user->canManage()) {
            abort(403, 'Hanya penulis atau super admin yang bisa menghapus komentar.');
        }

        $comment->delete();

        return redirect()->back()->with('status', 'Komentar dihapus.');
    }
}

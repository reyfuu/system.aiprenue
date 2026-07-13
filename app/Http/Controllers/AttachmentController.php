<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\PipelineAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// Lampiran file kartu — CRUD dibatasi super admin/IT (via EnsureMenuAccess).
class AttachmentController extends Controller
{
    // Unggah file & catat metadata
    public function store(Request $request, Pipeline $pipeline)
    {
        $request->validate([
            'file' => 'required|file|max:10240',                 // maks 10 MB
        ]);

        $file = $request->file('file');                          // file terunggah
        $path = $file->store('attachments', 'public');           // simpan di storage/app/public/attachments

        $pipeline->attachments()->create([
            'user_id' => $request->user()->id,                   // pengunggah
            'path'    => $path,                                   // path relatif disk public
            'name'    => $file->getClientOriginalName(),          // nama asli
            'mime'    => $file->getClientMimeType(),              // tipe mime
            'size'    => $file->getSize(),                        // ukuran byte
        ]);

        return redirect()->back()->with('status', 'Lampiran diunggah.');
    }

    // Hapus file + record
    public function destroy(PipelineAttachment $attachment)
    {
        Storage::disk('public')->delete($attachment->path);      // hapus file fisik
        $attachment->delete();                                    // hapus record

        return redirect()->back()->with('status', 'Lampiran dihapus.');
    }
}

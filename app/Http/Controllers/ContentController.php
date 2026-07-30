<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Kalender produksi Content: tabel, filter minggu, dan CRUD modal. */
class ContentController extends Controller
{
    public function index(Request $request)
    {
        $query = Content::query()->latest('tanggal_upload')->latest('id');
        $week = $request->string('week')->toString();

        // Input type="week" mengirim YYYY-Www. ISO week menjaga Senin–Minggu
        // konsisten termasuk pada pergantian tahun.
        if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $match)) {
            $awal = Carbon::now()->setISODate((int) $match[1], (int) $match[2])->startOfDay();
            $query->whereDate('tanggal_upload', '>=', $awal->toDateString())
                ->whereDate('tanggal_upload', '<=', $awal->copy()->addDays(6)->toDateString());
        }

        return Inertia::render('Content/Index', [
            'contents' => $query->paginate(15)->withQueryString(),
            'filters' => ['week' => $week],
            'progressOptions' => Content::PROGRESS,
            'canManageContent' => $request->user()->canManageMenu('content'),
        ]);
    }

    public function store(Request $request)
    {
        Content::create($request->validate($this->rules()));

        return back()->with('status', 'Content ditambahkan.');
    }

    public function update(Request $request, Content $content)
    {
        $content->update($request->validate($this->rules()));

        return back()->with('status', 'Content diperbarui.');
    }

    public function destroy(Content $content)
    {
        $content->delete();

        return back()->with('status', 'Content dihapus.');
    }

    /** Semua kolom spreadsheet boleh dilengkapi bertahap; progress tetap wajib. */
    private function rules(): array
    {
        return [
            'comp' => 'nullable|string|max:150',
            'jenis_postingan' => 'nullable|string|max:100',
            'kategori' => 'nullable|string|max:100',
            'referensi' => 'nullable|string|max:5000',
            'inti_pesan' => 'nullable|string|max:5000',
            'hook_material' => 'nullable|string|max:10000',
            'brief_original' => 'nullable|string|max:50000',
            'opsi_brief' => 'nullable|string|max:50000',
            'script_remake' => 'nullable|string|max:50000',
            'editor' => 'nullable|string|max:150',
            'progress' => ['required', Rule::in(array_keys(Content::PROGRESS))],
            'tanggal_upload' => 'nullable|date',
            'link_hasil_editing' => 'nullable|string|max:5000',
            'link_b_roll' => 'nullable|string|max:5000',
            'caption' => 'nullable|string|max:50000',
            'link_ai_kata_kunci' => 'nullable|string|max:5000',
        ];
    }
}

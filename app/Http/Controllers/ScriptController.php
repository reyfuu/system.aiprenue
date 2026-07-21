<?php

namespace App\Http\Controllers;

use App\Models\Script;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

// Naskah per brand. Isinya datang dari agen Daily Script Rave (POST /api/scripts)
// — halaman ini menampilkan satu berkas PDF per paket.
class ScriptController extends Controller
{
    /** Galeri brand + jumlah naskahnya. */
    public function index()
    {
        $counts = Script::selectRaw('brand, COUNT(*) as total')->groupBy('brand')->pluck('total', 'brand');
        $latest = Script::selectRaw('brand, MAX(generated_for) as tgl')->groupBy('brand')->pluck('tgl', 'brand');

        return Inertia::render('Script', [
            'brands' => collect(Script::BRANDS)->map(fn ($label, $key) => [
                'key' => $key,
                'name' => $label,
                'count' => $counts[$key] ?? 0,
                // paket terbaru: jawaban cepat utk "agennya masih jalan tidak?"
                'latest' => ($t = $latest[$key] ?? null) ? Carbon::parse($t)->translatedFormat('d M Y') : null,
            ])->values(),
        ]);
    }

    /** Daftar paket PDF satu brand. Isi naskah baru diambil saat PDF diminta. */
    public function show(string $brand)
    {
        abort_unless(array_key_exists($brand, Script::BRANDS), 404, 'Brand tak dikenal.');

        $nama = Script::BRANDS[$brand];
        $packs = Script::where('brand', $brand)
            ->selectRaw('generated_for, COUNT(*) as total')
            ->groupBy('generated_for')
            ->orderByDesc('generated_for')
            ->get();

        return Inertia::render('ScriptBrand', [
            'brand' => ['key' => $brand, 'name' => $nama],
            'canManage' => request()->user()->canManage(),
            'uploadUrl' => route('script.upload', $brand),
            'packs' => $packs->map(function ($pack) use ($brand, $nama) {
                $tanggal = $pack->generated_for->toDateString();

                return [
                    'date' => $tanggal,
                    'label' => $pack->generated_for->translatedFormat('d M Y'),
                    'count' => (int) $pack->total,
                    'name' => "Script-{$nama}-{$tanggal}.pdf",
                    // Proyek ini tanpa Ziggy; URL final dikirim sebagai prop.
                    'pdf' => route('script.pdf', [$brand, $tanggal]),
                ];
            }),
        ]);
    }

    /** Upload PDF manual untuk paket script brand. MVP: sistem menyimpan file
     *  asli agar bisa diunduh dari menu Script; isi PDF tidak diparse jadi 30
     *  naskah karena PHP/Laravel app ini belum punya parser PDF di dependency. */
    public function upload(Request $request, string $brand)
    {
        abort_unless(array_key_exists($brand, Script::BRANDS), 404, 'Brand tak dikenal.');
        abort_unless($request->user()->canManage(), 403);

        $data = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:25600'],
            'generated_for' => ['nullable', 'date'],
        ]);

        $tanggal = Carbon::parse($data['generated_for'] ?? today())->toDateString();
        $path = $request->file('pdf')->store("scripts/{$brand}", 'public');

        DB::transaction(function () use ($brand, $tanggal, $path) {
            $lama = Script::where('brand', $brand)->where('generated_for', $tanggal)->pluck('source_pdf_path')->filter()->unique();
            Script::where('brand', $brand)->where('generated_for', $tanggal)->delete();
            $lama->each(fn ($file) => Storage::disk('public')->delete($file));

            Script::create([
                'brand' => $brand,
                'title' => 'PDF Upload Manual',
                'body' => 'Paket ini berasal dari PDF yang diupload manual.',
                'generated_for' => $tanggal,
                'source_pdf_path' => $path,
            ]);
        });

        return back()->with('status', 'PDF script berhasil diupload.');
    }

    /** Satu paket (brand + tanggal) jadi satu PDF — pengganti dokumen Drive
     *  yang dulu dibuat agen. PDF dibuka inline agar bisa ditinjau sebelum diunduh. */
    public function pdf(string $brand, string $date)
    {
        abort_unless(array_key_exists($brand, Script::BRANDS), 404, 'Brand tak dikenal.');
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 404);

        $scripts = Script::where('brand', $brand)->whereDate('generated_for', $date)
            ->orderBy('id')   // urutan kirim dari agen = urutan nomor naskah
            ->get(['title', 'body', 'source_pdf_path']);

        abort_if($scripts->isEmpty(), 404, 'Paket naskah tak ditemukan.');

        $nama = Script::BRANDS[$brand];

        if ($file = $scripts->first()->source_pdf_path) {
            abort_unless(Storage::disk('public')->exists($file), 404, 'File PDF tak ditemukan.');

            return response(Storage::disk('public')->get($file), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Script-'.$nama.'-'.$date.'.pdf"',
            ]);
        }

        return Pdf::loadView('script.report', [
            'brand' => $nama,
            'tanggal' => Carbon::parse($date)->translatedFormat('d F Y'),
            'scripts' => $scripts,
        ])->setPaper('a4', 'portrait')
            ->stream("Script-{$nama}-{$date}.pdf");
    }
}

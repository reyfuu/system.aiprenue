<?php

namespace App\Http\Controllers;

use App\Models\Script;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Naskah per brand. Isinya datang dari agen Daily Script Rave (POST /api/scripts)
// — halaman ini cuma membaca & menghapus.
class ScriptController extends Controller
{
    /** Galeri brand + jumlah naskahnya. */
    public function index()
    {
        $counts = Script::selectRaw('brand, COUNT(*) as total')->groupBy('brand')->pluck('total', 'brand');
        $latest = Script::selectRaw('brand, MAX(generated_for) as tgl')->groupBy('brand')->pluck('tgl', 'brand');

        return Inertia::render('Script', [
            'brands' => collect(Script::BRANDS)->map(fn ($label, $key) => [
                'key'    => $key,
                'name'   => $label,
                'count'  => $counts[$key] ?? 0,
                // paket terbaru: jawaban cepat utk "agennya masih jalan tidak?"
                'latest' => ($t = $latest[$key] ?? null) ? \Carbon\Carbon::parse($t)->translatedFormat('d M Y') : null,
            ])->values(),
        ]);
    }

    /** Daftar naskah satu brand, dikelompokkan per paket (tanggal). */
    public function show(Request $request, string $brand)
    {
        abort_unless(array_key_exists($brand, Script::BRANDS), 404, 'Brand tak dikenal.');

        $scripts = Script::where('brand', $brand)
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($w) => $w->where('title', 'like', "%{$request->search}%")
                    ->orWhere('body', 'like', "%{$request->search}%")
            ))
            // terbaru dulu; `id` sbg pemecah seri supaya urutan dlm satu paket stabil
            ->orderByDesc('generated_for')->orderBy('id')
            ->get(['id', 'title', 'body', 'generated_for']);

        return Inertia::render('ScriptBrand', [
            'brand'     => ['key' => $brand, 'name' => Script::BRANDS[$brand]],
            'filters'   => $request->only('search'),
            'canManage' => auth()->user()->canManage(),
            // Dikelompokkan di server: Vue tinggal me-render, tak perlu tahu
            // aturan pengelompokannya.
            'packs' => $scripts->groupBy(fn ($s) => $s->generated_for->toDateString())
                ->map(fn ($rows, $tgl) => [
                    'date'  => $tgl,
                    'label' => \Carbon\Carbon::parse($tgl)->translatedFormat('d M Y'),
                    // URL dirakit di server: proyek ini tak pakai Ziggy, jadi
                    // helper route() tak ada di Vue (pola yg sama dgn reportUrl
                    // di Pembukuan).
                    'pdf'   => route('script.pdf', [$brand, $tgl]),
                    'items' => $rows->map(fn ($s) => [
                        'id'    => $s->id,
                        'title' => $s->title,
                        'body'  => $s->body,
                    ])->values(),
                ])->values(),
        ]);
    }

    /** Satu paket (brand + tanggal) jadi satu PDF — pengganti dokumen Drive
     *  yang dulu dibuat agen. Sengaja dirakit dari isi tabel, bukan file yang
     *  diunggah agen: naskah yang dihapus lewat UI ikut hilang dari PDF-nya,
     *  sementara file statis akan terus memajang yang sudah dibuang.
     *
     *  Tak ikut memakai filter `search` milik show(): yang diminta paket utuh,
     *  bukan potongan hasil pencarian. */
    public function pdf(string $brand, string $date)
    {
        abort_unless(array_key_exists($brand, Script::BRANDS), 404, 'Brand tak dikenal.');

        $scripts = Script::where('brand', $brand)->where('generated_for', $date)
            ->orderBy('id')   // urutan kirim dari agen = urutan nomor naskah
            ->get(['title', 'body']);

        abort_if($scripts->isEmpty(), 404, 'Paket naskah tak ditemukan.');

        $nama = Script::BRANDS[$brand];

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('script.report', [
            'brand'   => $nama,
            'tanggal' => \Carbon\Carbon::parse($date)->translatedFormat('d F Y'),
            'scripts' => $scripts,
        ])->setPaper('a4', 'portrait')
            ->download("Script-{$nama}-{$date}.pdf");
    }

    public function destroy(Script $script)
    {
        $script->delete();

        return back()->with('status', 'Naskah dihapus.');
    }
}

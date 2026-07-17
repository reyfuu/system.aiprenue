<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        /* DejaVu Sans: satu-satunya font bawaan DomPDF yang punya glyph lengkap.
           Naskah memakai "·" sebagai pemisah kategori & "–" di judul seksi —
           dengan font lain keduanya jadi kotak kosong. */
        * { font-family: DejaVu Sans, sans-serif; }
        /* Paket berisi 30 naskah: ukuran dan jarak dibuat rapat supaya beberapa
           naskah muat per halaman tanpa mengorbankan keterbacaan saat dicetak. */
        @page { margin: 24px 28px; }
        body { color: #1e293b; font-size: 8px; margin: 0; }

        h1 { font-size: 14px; margin: 0; color: #1d4ed8; }
        .muted { color: #64748b; font-size: 8px; }
        .head { border-bottom: 1px solid #1d4ed8; padding-bottom: 4px; margin-bottom: 2px; }

        /* Jangan paksa satu naskah satu halaman. Judul tetap menempel pada awal
           body, sedangkan body panjang boleh berlanjut agar ruang tidak boros. */
        .script { margin-top: 6px; }
        .script h2 {
            page-break-after: avoid; font-size: 9px; margin: 0 0 2px; color: #1d4ed8;
            background: #eef2ff; padding: 3px 5px; border-radius: 2px;
        }
        /* white-space: pre-wrap — naskah disimpan sebagai teks polos & barisnya
           bermakna (HOOK:, SCRIPT:, CTA: masing-masing satu baris). Tanpa ini
           HTML meratakan semuanya jadi satu paragraf yang tak terbaca. */
        .body { white-space: pre-wrap; line-height: 1.25; padding: 0 5px; }
    </style>
</head>
<body>
    {{-- Kepala dokumen: brand + tanggal paket + jumlah naskah --}}
    <div class="head">
        <h1>Script Pack — {{ $brand }}</h1>
        <div class="muted">{{ $tanggal }} · {{ $scripts->count() }} script</div>
    </div>

    {{-- Satu blok per naskah: judul sebagai kepala, body apa adanya --}}
    @foreach ($scripts as $s)
        <div class="script">
            <h2>{{ $s->title }}</h2>
            <div class="body">{{ $s->body }}</div>
        </div>
    @endforeach
</body>
</html>

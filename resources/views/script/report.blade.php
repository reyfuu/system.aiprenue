<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        /* DejaVu Sans: satu-satunya font bawaan DomPDF yang punya glyph lengkap.
           Naskah memakai "·" sebagai pemisah kategori & "–" di judul seksi —
           dengan font lain keduanya jadi kotak kosong. */
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 11px; }

        h1 { font-size: 18px; margin: 0; color: #1d4ed8; }
        .muted { color: #64748b; font-size: 10px; }
        .head { border-bottom: 2px solid #1d4ed8; padding-bottom: 8px; margin-bottom: 4px; }

        /* Efeknya di DomPDF: praktis satu naskah per halaman (diukur — 3 naskah
           jadi 3 halaman; tanpa aturan ini 2 halaman, tapi ada naskah terbelah
           di tengah kalimat). Halaman jadi lebih banyak, dan itu memang yang
           dipilih: paket ini dibaca & dicetak per naskah, bukan sebagai prosa
           bersambung. Naskah 30-45 detik selalu muat dalam satu halaman. */
        .script { page-break-inside: avoid; margin-top: 16px; }
        .script h2 {
            font-size: 12px; margin: 0 0 6px; color: #1d4ed8;
            background: #eef2ff; padding: 6px 8px; border-radius: 3px;
        }
        /* white-space: pre-wrap — naskah disimpan sebagai teks polos & barisnya
           bermakna (HOOK:, SCRIPT:, CTA: masing-masing satu baris). Tanpa ini
           HTML meratakan semuanya jadi satu paragraf yang tak terbaca. */
        .body { white-space: pre-wrap; line-height: 1.5; padding: 0 8px; }
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

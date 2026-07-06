<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1e293b; font-size: 11px; margin: 0; }
    .header { background: #1d4ed8; color: #fff; padding: 18px 24px; }
    .header h1 { margin: 0; font-size: 20px; }
    .header p { margin: 4px 0 0; font-size: 11px; color: #dbeafe; }
    .wrap { padding: 18px 24px; }
    .cards { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 14px; }
    .card { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px; width: 33%; }
    .card .lbl { font-size: 10px; color: #475569; }
    .card .val { font-size: 16px; font-weight: bold; color: #1d4ed8; margin-top: 4px; }
    .card.grand { background: #1d4ed8; border-color: #1d4ed8; }
    .card.grand .lbl { color: #dbeafe; }
    .card.grand .val { color: #fff; }
    h2 { font-size: 13px; color: #1e40af; margin: 16px 0 6px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #1d4ed8; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; text-transform: uppercase; }
    table.data td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
    table.data tr:nth-child(even) td { background: #f8fafc; }
    .right { text-align: right; }
    .badge { background: #dbeafe; color: #1d4ed8; padding: 1px 6px; border-radius: 10px; font-size: 9px; }
    .totalrow td { font-weight: bold; background: #eff6ff !important; border-top: 2px solid #1d4ed8; }
    .foot { margin-top: 12px; font-size: 9px; color: #94a3b8; }
</style>
</head>
<body>
    <div class="header">
        <h1>REPORT OMZET — PIPELINE FK-AI PRENEUR</h1>
        <p>
            Kategori: {{ $category ? \App\Models\Pipeline::CATEGORIES[$category] : 'Semua (Endorse + Agensi)' }}
            &nbsp;•&nbsp; Dibuat: {{ $generated }}
            &nbsp;•&nbsp; Kurs USD→IDR: Rp {{ number_format($kurs, 0, ',', '.') }}
        </p>
    </div>

    <div class="wrap">
        {{-- Ringkasan omzet --}}
        <table class="cards">
            <tr>
                <td class="card">
                    <div class="lbl">OMZET IDR</div>
                    <div class="val">Rp {{ number_format($totalIdr, 0, ',', '.') }}</div>
                </td>
                <td class="card">
                    <div class="lbl">OMZET USD</div>
                    <div class="val">$ {{ number_format($totalUsd, 2) }}</div>
                </td>
                <td class="card grand">
                    <div class="lbl">TOTAL OMZET (dalam IDR)</div>
                    <div class="val">Rp {{ number_format($grandIdr, 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>

        {{-- Breakdown per account --}}
        <h2>Rincian per Account</h2>
        <table class="data">
            <thead>
                <tr><th>Account</th><th class="right">Jumlah Entri</th><th class="right">Omzet IDR</th><th class="right">Omzet USD</th></tr>
            </thead>
            <tbody>
                @foreach ($perAccount as $a)
                    <tr>
                        <td>{{ \App\Models\Pipeline::ACCOUNTS[$a->account] ?? $a->account }}</td>
                        <td class="right">{{ $a->jml }}</td>
                        <td class="right">Rp {{ number_format($a->idr, 0, ',', '.') }}</td>
                        <td class="right">$ {{ number_format($a->usd, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="totalrow">
                    <td>TOTAL</td>
                    <td class="right">{{ $rows->count() }}</td>
                    <td class="right">Rp {{ number_format($totalIdr, 0, ',', '.') }}</td>
                    <td class="right">$ {{ number_format($totalUsd, 2) }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Detail entri --}}
        <h2>Detail Entri ({{ $rows->count() }})</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Kategori</th><th>Account</th><th>Endorse</th><th>Output</th>
                    <th>Progress</th><th>Payment</th><th class="right">IDR</th><th class="right">USD</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $p)
                    <tr>
                        <td><span class="badge">{{ \App\Models\Pipeline::CATEGORIES[$p->category] }}</span></td>
                        <td>{{ \App\Models\Pipeline::ACCOUNTS[$p->account] }}</td>
                        <td>{{ $p->endorse }}</td>
                        <td>{{ $p->outputs->pluck('name')->implode(', ') }}</td>
                        <td>{{ \App\Models\Pipeline::PROGRESS[$p->progress] }}</td>
                        <td>{{ \App\Models\Pipeline::PAYMENT[$p->payment_status] }}</td>
                        <td class="right">{{ $p->amount_idr ? 'Rp '.number_format($p->amount_idr, 0, ',', '.') : '—' }}</td>
                        <td class="right">{{ $p->amount_usd ? '$'.number_format($p->amount_usd, 2) : '—' }}</td>
                    </tr>
                @endforeach
                <tr class="totalrow">
                    <td colspan="6">TOTAL OMZET</td>
                    <td class="right">Rp {{ number_format($totalIdr, 0, ',', '.') }}</td>
                    <td class="right">$ {{ number_format($totalUsd, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <p class="foot">Grand total dikonversi ke IDR memakai kurs Rp {{ number_format($kurs, 0, ',', '.') }}/USD. Ubah dengan parameter ?kurs= pada URL report.</p>
    </div>
</body>
</html>

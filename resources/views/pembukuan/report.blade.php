@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 12px; }
        h1 { font-size: 18px; margin: 0; color: #1d4ed8; }
        .muted { color: #64748b; font-size: 10px; }
        .cards { width: 100%; margin: 16px 0; border-collapse: collapse; }
        .cards td { border: 1px solid #dbeafe; padding: 10px; width: 25%; }
        .cards .label { color: #64748b; font-size: 10px; }
        .cards .val { font-size: 14px; font-weight: bold; color: #1d4ed8; }
        table.data { width: 100%; border-collapse: collapse; margin: 8px 0 18px; }
        table.data th { background: #1d4ed8; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #eef2ff; }
        .r { text-align: right; }
        h2 { font-size: 13px; margin: 14px 0 4px; color: #334155; }
    </style>
</head>
<body>
    <h1>Pembukuan — Keuangan</h1>
    <p class="muted">Dibuat {{ $d['summary']['generated'] }}</p>

    <table class="cards">
        <tr>
            <td><div class="label">Total Pemasukan</div><div class="val">{{ $rp($d['summary']['totalIn']) }}</div></td>
            <td><div class="label">Total Pengeluaran</div><div class="val">{{ $rp($d['summary']['totalOut']) }}</div></td>
            <td><div class="label">Laba/Rugi</div><div class="val">{{ $rp($d['summary']['laba']) }}</div></td>
            <td><div class="label">Nilai Inventaris ({{ $d['summary']['invMonthLabel'] }})</div><div class="val">{{ $rp($d['summary']['invTotal']) }}</div></td>
        </tr>
    </table>

    <h2>Laba/Rugi per Bulan</h2>
    <table class="data">
        <thead><tr><th>Bulan</th><th class="r">Pemasukan</th><th class="r">Pengeluaran</th><th class="r">Laba</th></tr></thead>
        <tbody>
            @forelse ($d['monthly'] as $m)
                <tr><td>{{ $m['label'] }}</td><td class="r">{{ $rp($m['pemasukan']) }}</td><td class="r">{{ $rp($m['pengeluaran']) }}</td><td class="r">{{ $rp($m['laba']) }}</td></tr>
            @empty
                <tr><td colspan="4">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Pemasukan per Kategori</h2>
    <table class="data">
        <thead><tr><th>Kategori</th><th class="r">Jumlah</th></tr></thead>
        <tbody>
            @foreach ($d['incomeByCat'] as $r)
                <tr><td>{{ $r['label'] }}</td><td class="r">{{ $rp($r['value']) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Pengeluaran per Kategori</h2>
    <table class="data">
        <thead><tr><th>Kategori</th><th class="r">Jumlah</th></tr></thead>
        <tbody>
            @foreach ($d['expenseByCat'] as $r)
                <tr><td>{{ $r['label'] }}</td><td class="r">{{ $rp($r['value']) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Inventaris Barang ({{ $d['summary']['invMonthLabel'] }})</h2>
    <table class="data">
        <thead><tr><th>Barang</th><th class="r">Qty</th><th class="r">Nilai</th></tr></thead>
        <tbody>
            @foreach ($d['inventory'] as $it)
                <tr><td>{{ $it['name'] }}</td><td class="r">{{ $it['qty'] }}</td><td class="r">{{ $rp($it['total']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

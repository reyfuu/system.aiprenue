// Fungsi format & status bersama halaman OKR. Dipisah dari komponen supaya
// tak diduplikasi di ObjectiveBlock / KeyResultRow / TrendSpark.

// Notasi ringkas (1,2 jt) karena view & omset lazimnya jutaan; angka penuh
// memaksa kolom melebar & lebih sulit dibaca sekilas.
export const nfShort = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
export const nfFull = new Intl.NumberFormat('id-ID');

export const fmt = (n, unit) => (unit === 'rupiah' ? 'Rp ' : '') + nfShort.format(Number(n || 0)) + (unit === 'persen' ? '%' : '');
export const fmtFull = (n, unit) => (unit === 'rupiah' ? 'Rp ' : '') + nfFull.format(Number(n || 0)) + (unit === 'persen' ? '%' : '');

// Lebar meter dibatasi 100% supaya capaian 300% tak menyeruak keluar; angka
// persennya sendiri tetap tampil apa adanya.
export const barWidth = (p) => Math.min(100, Math.max(0, Number(p || 0))) + '%';

// Warna isi meter. null (target belum ditetapkan) sengaja abu-abu, bukan merah.
// Kelas-kelas ini sudah di-safelist di app.css (@source inline).
export const barColor = (p) => {
    if (p === null || p === undefined) return 'bg-slate-300';
    if (p >= 100) return 'bg-emerald-500';
    if (p >= 60) return 'bg-amber-500';
    return 'bg-red-500';
};

// Warna & label status (nada laporan, shade lebih dalam). Ambang sama persis
// dgn perhitungan server: ≥100 tercapai / ≥60 berjalan / <60 perlu perhatian.
export const statusText = (p) => {
    if (p === null || p === undefined) return 'text-slate-400';
    if (p >= 100) return 'text-emerald-700';
    if (p >= 60) return 'text-amber-700';
    return 'text-red-700';
};
export const statusLabel = (p) => {
    if (p === null || p === undefined) return 'Tanpa target';
    if (p >= 100) return 'Tercapai';
    if (p >= 60) return 'Berjalan';
    return 'Perlu perhatian';
};

// Sumber KR auto: omset dari Pembukuan, sisanya (view/subscriber) dari Insight.
export const sumberAuto = (kr) => (kr.metric === 'omset' ? 'Auto · Pembukuan' : 'Auto · Insight');

// Membangun geometri sparkline dari titik target/actual satu metrik. Skala
// mengikuti rentang metrik itu sendiri (bukan nol) supaya gerak antar kuartal
// terbaca; tiap metrik punya sumbunya sendiri (view ratusan ribu, omset ratusan
// juta) — persis alasan dulu memakai toggle metrik.
export const buatSpark = (points) => {
    const W = 220, H = 64, pad = 6;
    const n = points.length;
    const nilai = points.flatMap((p) => [Number(p.target) || 0, Number(p.actual) || 0]);
    const max = Math.max(...nilai) * 1.08 || 1;
    const min = Math.min(...nilai) * 0.9;
    const rentang = max - min || 1;
    const x = (i) => pad + (W - pad * 2) * (i / (n - 1 || 1));
    const y = (v) => pad + (H - pad * 2) * (1 - (v - min) / rentang);
    const jalur = (key) => points.map((p, i) => (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(Number(p[key]) || 0).toFixed(1)).join(' ');
    const actualPath = jalur('actual');
    const targetPath = jalur('target');
    const area = `${actualPath} L${x(n - 1).toFixed(1)} ${H - pad} L${x(0).toFixed(1)} ${H - pad} Z`;
    return { W, H, actualPath, targetPath, area, lastX: x(n - 1), lastY: y(Number(points[n - 1]?.actual) || 0) };
};

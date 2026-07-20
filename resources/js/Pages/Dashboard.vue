<script setup>
// Halaman Dashboard — ringkasan atas + kartu per modul (Pipeline, Kanban, Order, Mindmap, Script, Pembukuan).
import { computed } from 'vue';               // computed untuk turunan reaktif
import { Link, router, usePage } from '@inertiajs/vue3'; // Link nav, router utk filter, usePage shared props
import Layout from '../Layout.vue';           // Layout sudah render sidebar + flash toast
import '../scripts/lib/charts';               // registrasi elemen Chart.js (dipakai bareng Pembukuan)
import { Line } from 'vue-chartjs';           // komponen chart siap pakai

// Props dari DashboardController — satu objek per modul
const props = defineProps({
    rate: [Number, String],                             // kurs USD→IDR
    monthly: { type: Array, default: () => [] },        // omzet per bulan: { label, perAccount, total }
    accounts: { type: Object, default: () => ({}) },    // key→label akun (urutan seri grafik)
    summary: { type: Object, default: () => ({}) },     // ringkasan atas (grandIdr, total, lunas, outstanding)
    pipeline: { type: Object, default: () => ({}) },    // { total, grandIdr, perCategory, categories }
    kanban: { type: Object, default: () => ({}) },      // { total, done, boards, perProgress, progresses }
    order: { type: Object, default: () => ({}) },       // { total, urgent, dp, nilai, perTipe, tipeOrder }
    mindmap: { type: Object, default: () => ({}) },     // { total, latest }
    script: { type: Object, default: () => ({}) },      // { folders, files }
    pembukuan: { type: Object, default: () => ({}) },   // { pemasukan, pengeluaran, laba, transaksi, invTotal }
    filter: { type: Object, default: () => ({ bulan: 'semua', opsi: [] }) }, // periode aktif + pilihannya
    daftar: { type: Object, default: null },            // drill-down: null = tampilkan grafik
});

// Satu pintu untuk semua navigasi dashboard. `bulan` dan `lihat` harus selalu
// dikirim bersamaan — kalau salah satu dijatuhkan, mengganti bulan akan menutup
// tabel, atau membuka tabel akan mereset periode ke "semua". Keduanya pernah
// jadi bug yang membingungkan karena angkanya berubah tanpa sebab yang terlihat.
const pindah = (ubah) => {
    const q = { bulan: props.filter.bulan, lihat: props.daftar?.kunci ?? '', ...ubah };
    router.get('/dashboard',
        Object.fromEntries(Object.entries(q).filter(([, v]) => v && v !== 'semua')),
        { preserveScroll: true, preserveState: false },
    );
};

// preserveState:false disengaja: seluruh angka halaman datang dari server & harus
// ikut berubah; mempertahankan state lama bikin sebagian kartu masih memperlihatkan
// periode sebelumnya.
const gantiBulan = (nilai) => pindah({ bulan: nilai });

// Klik kartu ringkasan → tabel order menggantikan grafik. Klik kartu yang sama
// lagi = menutup (toggle), jadi tak perlu mencari tombol tutup.
const bukaDaftar = (kunci) => pindah({ lihat: props.daftar?.kunci === kunci ? '' : kunci });

// Grand Omzet = jumlah semuanya, jadi mengkliknya berarti "kembali ke tampilan
// utuh": tutup drill-down apa pun, kembalikan grafik. Dijaga `props.daftar` —
// tanpa itu, klik saat grafik sudah tampil memicu round-trip ke server yang
// hasilnya sama persis dengan yang sedang dilihat.
const tutupDaftar = () => { if (props.daftar) pindah({ lihat: '' }); };

// Label periode aktif untuk penanda di samping dropdown
const labelBulanAktif = computed(
    () => props.filter.opsi.find((o) => o.value === props.filter.bulan)?.label ?? props.filter.bulan,
);

// Peta menu yang boleh dilihat user → kartu modul ikut digating seperti sidebar
const page = usePage();
const menus = computed(() => page.props.auth?.user?.menus ?? {});

// Helper format Rupiah: 1234567 → "Rp 1.234.567"
const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

// ---- Grafik omzet per bulan: satu garis per akun ----
const adaMonthly = computed(() => props.monthly.length > 0);
// Warna seri: samakan dgn badge akun di kartu Sales (FK brand, AI Preneur slate).
const WARNA_AKUN = { fk: '#4f46e5', ai_preneur: '#64748b' };
const lineData = computed(() => ({
    labels: props.monthly.map((m) => m.label),
    datasets: Object.entries(props.accounts).map(([key, label]) => {
        const warna = WARNA_AKUN[key] ?? '#94a3b8';

        return {
            label,
            data: props.monthly.map((m) => m.perAccount?.[key] ?? 0),
            borderColor: warna,
            backgroundColor: warna,   // warna titik & kotak legend
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.3,             // sedikit melengkung; 0 bikin patah-patah
        };
    }),
}));
const lineOpts = {
    responsive: true,
    maintainAspectRatio: false,   // wajib false agar chart mengikuti tinggi container
    interaction: { mode: 'index', intersect: false },   // hover di mana saja pada bulan itu → dua garis sekaligus
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, usePointStyle: true } },
        tooltip: {
            callbacks: {
                label: (c) => `${c.dataset.label}: ${rp(c.parsed.y)}`,
                // Garis tak ditumpuk, jadi total bulan tak terbaca dari tingginya —
                // taruh di footer, karena itu justru pertanyaannya: "bulan ini berapa?"
                footer: (items) => 'Total: ' + rp(items.reduce((s, i) => s + i.parsed.y, 0)),
            },
        },
    },
    scales: {
        x: { grid: { display: false } },
        y: {
            beginAtZero: true,   // tanpa ini sumbu mulai dari nilai terendah & selisihnya terlihat berlebihan
            ticks: { callback: (v) => 'Rp ' + Number(v).toLocaleString('id-ID') },
            grid: { color: '#eef2ff' },
        },
    },
};

// Warna titik/bar per progress kanban
const progressDot = {
    script: 'bg-purple-500',
    editing: 'bg-sky-500',
    progress: 'bg-brand-600',
    pending: 'bg-amber-500',
    done: 'bg-emerald-500',
};

// Warna titik per tipe order (samakan dengan Order::TIPE_COLORS)
const tipeDot = {
    coaching: 'bg-brand-600',
    endorse: 'bg-emerald-500',
    speaker: 'bg-amber-500',
    agency: 'bg-rose-500',
};

// Persentase bar progress kanban: jumlah task progress ini / total task × 100
const pctOf = (pk) => {
    const c = props.kanban.perProgress?.[pk] ?? 0;
    return props.kanban.total ? Math.round((c / props.kanban.total) * 100) : 0;
};

// Laba positif → hijau, rugi → merah
const labaPositif = computed(() => (props.pembukuan.laba ?? 0) >= 0);
</script>

<template>
    <Layout title="Dashboard">
        <!-- Header gradient -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">DASHBOARD</h1>
                <p class="text-brand-100 text-sm">Ringkasan sistem AI Preneur — kurs 1 USD = {{ rp(rate) }}</p>
            </div>
        </header>

        <div class="px-6 py-6 space-y-6">
            <!-- ===== Filter periode. Mempersempit angka ringkasan & kartu Order;
                 grafik tren di bawah SENGAJA tetap semua bulan (lihat komentar
                 di controller). ===== -->
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm font-medium text-slate-600">Periode</label>
                <select
                    :value="filter.bulan"
                    @change="gantiBulan($event.target.value)"
                    class="border border-brand-100 bg-white rounded-xl px-3 py-2 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-brand-400 outline-none"
                >
                    <option value="semua">Semua bulan</option>
                    <option v-for="o in filter.opsi" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>

                <!-- Penanda periode aktif: tanpa ini, angka yang mengecil gampang
                     disangka data hilang, bukan hasil filter. -->
                <span v-if="filter.bulan !== 'semua'" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold pl-2.5 pr-1 py-1 rounded-lg bg-brand-50 text-brand-700 border border-brand-100">
                    Menampilkan {{ labelBulanAktif }}
                    <!-- Sasaran klik minimal 24x24 (w-6 h-6). Dulu cuma glyph &times;
                         telanjang tanpa padding — lebarnya ~8px, praktis mustahil
                         dikenai di trackpad. Padding kanan chip dikurangi (pr-1)
                         supaya tombol yang membesar tak bikin chip terlihat gemuk. -->
                    <button
                        @click="gantiBulan('semua')"
                        title="Tampilkan semua bulan"
                        aria-label="Hapus filter periode"
                        class="inline-flex items-center justify-center w-6 h-6 rounded-md text-base leading-none text-brand-500 hover:text-brand-800 hover:bg-brand-100 focus:ring-2 focus:ring-brand-400 outline-none"
                    >&times;</button>
                </span>
                <span v-else class="text-xs text-slate-400">Semua order sejak awal</span>
            </div>

            <!-- ===== Ringkasan cepat: SEMUA dari Order (omzet nyata), bukan Sales.
                 Sales = corong prospek yang nilainya masih estimasi & bisa batal. ===== -->
            <!-- 6 kolom baru dipakai di 2xl (>=1536px). Di 1366 — laptop paling
                 umum — 6 kolom menyisakan ~168px per kartu setelah dipotong
                 sidebar 224px, dan angka omzet 9 digit tidak muat di situ.
                 lg (1024) dan xl (1280) sengaja dilewat: keduanya masih terlalu
                 sempit. Di bawah 2xl tampil 3 kolom / 2 baris, tetap terbaca. -->
            <div class="grid grid-cols-2 sm:grid-cols-3 2xl:grid-cols-6 gap-3">
                <!-- Grand omzet (sengaja paling kiri). Merangkap tombol "kembali ke
                     grafik": ia jumlah dari semua kartu lain, jadi mengkliknya wajar
                     diartikan "tampilkan semuanya lagi". Saat tak ada drill-down,
                     tombolnya sengaja tidak memberi isyarat bisa diklik (cursor-default,
                     tanpa hover) — kalau tidak, orang mengkliknya lalu tak terjadi apa-apa
                     dan mengira ada yang rusak. -->
                <button @click="tutupDaftar" :disabled="!daftar"
                     :title="daftar ? 'Kembali ke grafik omzet' : null"
                     :class="['text-left bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white outline-none transition',
                              daftar ? 'cursor-pointer hover:shadow-md hover:brightness-110 focus:ring-2 focus:ring-brand-300' : 'cursor-default']">
                    <p class="text-xs text-brand-100 font-medium">
                        Grand Omzet (IDR)
                        <span v-if="daftar" class="ml-1 opacity-80">· ← grafik</span>
                    </p>
                    <p class="text-2xl font-bold mt-1">{{ rp(summary.grandIdr) }}</p>
                </button>
                <!-- Omzet per akun: pecahan Grand Omzet, jadi ditaruh tepat di sebelahnya.
                     FK + AI Preneur selalu = Grand Omzet. Dirender dari daftar akun, bukan
                     dua blok disalin — nambah akun cukup di Order::ACCOUNTS. -->
                <button v-for="(akun, key) in summary.perAccount" :key="key"
                     @click="bukaDaftar(key)"
                     :aria-pressed="daftar?.kunci === key"
                     :class="['text-left bg-white rounded-2xl shadow-sm border p-4 transition hover:border-brand-300 hover:shadow focus:ring-2 focus:ring-brand-400 outline-none',
                              daftar?.kunci === key ? 'border-brand-500 ring-2 ring-brand-200' : 'border-brand-100']">
                    <p class="text-xs text-slate-500 font-medium">Omzet {{ akun.label }}</p>
                    <!-- JANGAN pasang `truncate` di angka uang. "Rp 91.046.8..."
                         terlihat seperti angka utuh padahal digitnya hilang —
                         salah baca yang tak disadari, lebih buruk daripada teks
                         yang membungkus ke baris berikutnya. -->
                    <p class="text-xl font-bold text-brand-700 mt-1">{{ rp(akun.grandIdr) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">{{ akun.total }} order</p>
                </button>
                <!-- Total order -->
                <button @click="bukaDaftar('all')" :aria-pressed="daftar?.kunci === 'all'"
                     :class="['text-left bg-white rounded-2xl shadow-sm border p-4 transition hover:border-brand-300 hover:shadow focus:ring-2 focus:ring-brand-400 outline-none',
                              daftar?.kunci === 'all' ? 'border-brand-500 ring-2 ring-brand-200' : 'border-brand-100']">
                    <p class="text-xs text-slate-500 font-medium">Total Order</p>
                    <p class="text-2xl font-bold text-brand-700 mt-1">{{ summary.total }}</p>
                </button>
                <!-- Lunas = tipe pembayaran Full (Order tak kenal status 'belum') -->
                <button @click="bukaDaftar('full')" :aria-pressed="daftar?.kunci === 'full'"
                     :class="['text-left bg-white rounded-2xl shadow-sm border p-4 transition hover:border-brand-300 hover:shadow focus:ring-2 focus:ring-brand-400 outline-none',
                              daftar?.kunci === 'full' ? 'border-brand-500 ring-2 ring-brand-200' : 'border-brand-100']">
                    <p class="text-xs text-slate-500 font-medium">Lunas (Full)</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.lunas }}<span class="text-sm text-slate-400 font-medium"> / {{ summary.total }}</span></p>
                </button>
                <!-- Outstanding = order yang baru DP -->
                <button @click="bukaDaftar('dp')" :aria-pressed="daftar?.kunci === 'dp'"
                     :class="['text-left bg-white rounded-2xl shadow-sm border p-4 transition hover:border-brand-300 hover:shadow focus:ring-2 focus:ring-brand-400 outline-none',
                              daftar?.kunci === 'dp' ? 'border-brand-500 ring-2 ring-brand-200' : 'border-brand-100']">
                    <p class="text-xs text-slate-500 font-medium">Outstanding (DP)</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ summary.outstanding }}</p>
                </button>
            </div>

            <!-- ===== Panel drill-down: tabel order MENGGANTIKAN grafik =====
                 Satu ruang dipakai bergantian, bukan tabel ditambahkan di bawah —
                 halaman tak jadi memanjang & mata tak perlu mencari ke mana daftarnya
                 muncul. Menutupnya: klik lagi kartu yang sama, atau tombol di sini. -->
            <div v-if="daftar" class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <div class="flex items-baseline justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-slate-700">{{ daftar.judul }}</h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ daftar.jumlah }} order{{ filter.bulan !== 'semua' ? ' · ' + labelBulanAktif : '' }}
                            <!-- Kalau terpotong, katakan terus terang & tunjukkan jalan
                                 ke daftar penuh. Diam-diam memotong = user mengira
                                 segitu saja ordernya. -->
                            <span v-if="daftar.jumlah > daftar.baris.length">
                                · menampilkan {{ daftar.baris.length }} terbaru,
                                <Link href="/orders" class="text-brand-600 hover:text-brand-800 font-medium">lihat semua →</Link>
                            </span>
                        </p>
                    </div>
                    <button @click="tutupDaftar" class="text-xs font-medium text-slate-500 hover:text-brand-700 whitespace-nowrap">
                        Tutup, tampilkan grafik
                    </button>
                </div>

                <!-- overflow-x-auto: tabel 6 kolom tak muat di layar sempit; biarkan
                     tabelnya yang menggeser, jangan seluruh halaman. -->
                <div class="overflow-x-auto -mx-1">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-slate-400 border-b border-brand-50">
                                <th class="text-left font-medium px-1 pb-2">Customer</th>
                                <th class="text-left font-medium px-1 pb-2">Tipe</th>
                                <th class="text-left font-medium px-1 pb-2">Akun</th>
                                <th class="text-left font-medium px-1 pb-2">Bayar</th>
                                <th class="text-right font-medium px-1 pb-2">Total</th>
                                <th class="text-right font-medium px-1 pb-2">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in daftar.baris" :key="b.id" class="border-b border-brand-50 last:border-0">
                                <td class="px-1 py-2 font-medium text-slate-700">{{ b.customer || '—' }}</td>
                                <td class="px-1 py-2 text-slate-500">{{ b.tipeOrder }}</td>
                                <td class="px-1 py-2 text-slate-500">{{ b.akun }}</td>
                                <td class="px-1 py-2">
                                    <span :class="['text-xs font-semibold px-2 py-0.5 rounded-md',
                                                   b.bayar === 'Full' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700']">
                                        {{ b.bayar }}
                                    </span>
                                </td>
                                <!-- tabular-nums: angka rata secara vertikal biar mudah dibandingkan -->
                                <td class="px-1 py-2 text-right font-semibold text-brand-700 tabular-nums whitespace-nowrap">{{ rp(b.totalIdr) }}</td>
                                <td class="px-1 py-2 text-right text-slate-400 tabular-nums whitespace-nowrap">{{ b.tanggal || '—' }}</td>
                            </tr>
                            <!-- Kosong itu hasil yang sah (mis. belum ada DP bulan ini),
                                 jadi katakan, jangan tampilkan tabel kosong tanpa kata. -->
                            <tr v-if="!daftar.baris.length">
                                <td colspan="6" class="px-1 py-6 text-center text-sm text-slate-400">
                                    Tidak ada order pada kriteria ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===== Grafik omzet per bulan ===== -->
            <div v-else class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <div class="flex items-baseline justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-slate-700">Omzet per Bulan</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Order per tanggal bayar, satu garis per akun · USD dikonversi kurs {{ rp(rate) }}</p>
                    </div>
                    <span class="text-xs text-slate-400 whitespace-nowrap">{{ monthly.length }} bulan</span>
                </div>
                <!-- h-64 + maintainAspectRatio:false → chart mengisi tinggi ini -->
                <div v-if="adaMonthly" class="h-64">
                    <Line :data="lineData" :options="lineOpts" />
                </div>
                <p v-else class="text-sm text-slate-400 py-10 text-center">Belum ada order untuk digrafikkan.</p>
            </div>

            <!-- ===== Kartu ringkasan per modul ===== -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- ---- Pipeline: entri per board + omzet ---- -->
                <Link v-if="menus.pipeline" href="/pipelines" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Pipeline</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ pipeline.total }} <span class="text-sm text-slate-400 font-medium">entri</span></p>
                    <!-- "Estimasi", bukan "Omzet": ini nilai corong Sales & bisa batal.
                         Omzet nyata = Order (ringkasan atas). Dua-duanya bernama Omzet
                         bikin angkanya terlihat saling bertentangan. -->
                    <p class="text-xs text-slate-400 mt-1">Estimasi {{ rp(pipeline.grandIdr) }}</p>
                    <div class="mt-4 space-y-2">
                        <!-- Loop board pipeline (label cv, key ck) -->
                        <div v-for="(cv, ck) in pipeline.categories" :key="ck" class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">{{ cv }}</span>
                            <span class="font-semibold text-slate-700">{{ pipeline.perCategory?.[ck] ?? 0 }}</span>
                        </div>
                    </div>
                </Link>

                <!-- ---- Kanban: task per progress ---- -->
                <Link v-if="menus.kanban" href="/pipelines/kanban" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Kanban</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ kanban.done }} <span class="text-sm text-slate-400 font-medium">/ {{ kanban.total }} done</span></p>
                    <p class="text-xs text-slate-400 mt-1">{{ kanban.boards }} board</p>
                    <div class="mt-4 space-y-2.5">
                        <!-- Loop progress standar (label pv, key pk) -->
                        <div v-for="(pv, pk) in kanban.progresses" :key="pk">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="flex items-center gap-1.5 text-slate-500">
                                    <span :class="'w-2 h-2 rounded-full ' + (progressDot[pk] || 'bg-slate-400')"></span>{{ pv }}
                                </span>
                                <span class="font-semibold text-slate-700">{{ kanban.perProgress?.[pk] ?? 0 }}</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-brand-50 overflow-hidden">
                                <!-- Bar isi: lebar = persentase task progress ini -->
                                <div :class="'h-full ' + (progressDot[pk] || 'bg-slate-400')" :style="{ width: pctOf(pk) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </Link>

                <!-- ---- Order: nilai pembayaran + order per tipe ---- -->
                <Link v-if="menus.order" href="/orders" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Order</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ order.total }} <span class="text-sm text-slate-400 font-medium">order</span></p>
                    <p class="text-xs text-slate-400 mt-1">Nilai {{ rp(order.nilai) }}</p>
                    <div class="mt-4 space-y-2">
                        <!-- Loop tipe order (label tv, key tk) -->
                        <div v-for="(tv, tk) in order.tipeOrder" :key="tk" class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-1.5 text-slate-500">
                                <span :class="'w-2 h-2 rounded-full ' + (tipeDot[tk] || 'bg-slate-400')"></span>{{ tv }}
                            </span>
                            <span class="font-semibold text-slate-700">{{ order.perTipe?.[tk] ?? 0 }}</span>
                        </div>
                        <!-- Pemisah + status yang perlu perhatian -->
                        <div class="flex items-center justify-between text-sm pt-2 border-t border-brand-50">
                            <span class="text-slate-500">Masih DP</span>
                            <span class="font-semibold text-amber-600">{{ order.dp }}</span>
                        </div>
                    </div>
                </Link>

                <!-- ---- Mindmap ---- -->
                <Link v-if="menus.mindmap" href="/mindmaps" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Mindmap</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ mindmap.total }} <span class="text-sm text-slate-400 font-medium">mindmap</span></p>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Terakhir diubah</span>
                            <!-- truncate: judul mindmap bisa panjang -->
                            <span class="font-semibold text-slate-700 truncate max-w-[55%]">{{ mindmap.latest || '—' }}</span>
                        </div>
                    </div>
                </Link>

                <!-- ---- Script ---- -->
                <Link v-if="menus.script" href="/script" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Script</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ script.files }} <span class="text-sm text-slate-400 font-medium">naskah</span></p>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Folder</span>
                            <span class="font-semibold text-slate-700">{{ script.folders }}</span>
                        </div>
                        <!-- Folder masih kosong = naskah belum dikirim Hermes agent -->
                        <p v-if="script.files === 0" class="text-xs text-slate-400 pt-1">Folder masih kosong — naskah belum diisi.</p>
                    </div>
                </Link>

                <!-- ---- Pembukuan: dari transaksi, bukan omzet pipeline ---- -->
                <Link v-if="menus.pembukuan" href="/pembukuan" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Pembukuan</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p :class="'text-3xl font-bold ' + (labaPositif ? 'text-brand-700' : 'text-red-600')">{{ rp(pembukuan.laba) }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ labaPositif ? 'Laba' : 'Rugi' }} · {{ pembukuan.transaksi }} transaksi</p>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Pemasukan</span>
                            <span class="font-semibold text-emerald-600">{{ rp(pembukuan.pemasukan) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Pengeluaran</span>
                            <span class="font-semibold text-red-600">{{ rp(pembukuan.pengeluaran) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Nilai Inventaris</span>
                            <span class="font-semibold text-slate-700">{{ rp(pembukuan.invTotal) }}</span>
                        </div>
                        <!-- Bedakan "belum ada data" dari "laba nol" -->
                        <p v-if="pembukuan.transaksi === 0" class="text-xs text-slate-400 pt-1">Belum ada transaksi tercatat.</p>
                    </div>
                </Link>
            </div>
        </div>
    </Layout>
</template>

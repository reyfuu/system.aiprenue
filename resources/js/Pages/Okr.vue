<script setup>
// Halaman OKR perusahaan per kuartal: Objective berisi Key Result.
//
// Realisasi KR bertipe `auto` DIHITUNG server dari modul Insight & Pembukuan —
// tak ada angka realisasi otomatis yang diketik manusia, jadi tak bisa basi
// saat data sumbernya dikoreksi. KR `manual` ada untuk target yang memang tak
// punya sumber data ("10 klien coaching baru").
//
// KPI board & rapor per orang ada di halaman terpisah (/kpi). Pemisahannya soal
// hak akses, bukan kerapian: halaman ini memuat omset & pertumbuhan audiens dan
// terkunci untuk owner + manager.
//
// Kuartal dipilih lewat querystring ?q=YYYY-Qn supaya tautannya bisa dibagikan
// dan tombol back browser tetap masuk akal — bukan state lokal Vue.
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import ModalWrap from '../ModalWrap.vue';
import '../scripts/lib/charts';                // registrasi elemen Chart.js (dipakai bareng Dashboard & Pembukuan)
import { Line } from 'vue-chartjs';

const props = defineProps({
    quarter: Object,          // { year, quarter, key, label }
    quarterOptions: { type: Array, default: () => [] },
    range: Object,            // { start, end } — rentang tanggal kuartal
    objectives: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    tren: { type: Array, default: () => [] },
    metrics: { type: Object, default: () => ({}) },   // key metrik otomatis → label
    sources: { type: Object, default: () => ({}) },   // auto | manual → label
    units: { type: Object, default: () => ({}) },
    canManage: Boolean,
    bisaSalin: Boolean,                               // kuartal ini kosong & kuartal lalu ada isinya
    kuartalLaluLabel: { type: String, default: '' },
});

// Salin Objective + target kuartal lalu ke kuartal ini. Dikonfirmasi dulu:
// ini menulis banyak baris sekaligus, dan targetnya perlu ditinjau ulang —
// bukan hal yang pantas terjadi karena satu klik tak sengaja.
const salinKuartalLalu = () => {
    if (!confirm(`Salin semua Objective & target dari ${props.kuartalLaluLabel} ke ${props.quarter.label}? Realisasinya tidak ikut disalin.`)) return;
    router.post('/okr/salin', { year: props.quarter.year, quarter: props.quarter.quarter }, { preserveScroll: true });
};

// ---- Format angka ----
// Notasi ringkas (1,2 jt) dipakai karena view & omset lazimnya jutaan — angka
// penuh memaksa kolom melebar dan justru lebih sulit dibaca sekilas. Angka
// penuhnya tetap tersedia lewat atribut title.
const nfShort = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
const nfFull = new Intl.NumberFormat('id-ID');
const fmt = (n, unit) => (unit === 'rupiah' ? 'Rp ' : '') + nfShort.format(Number(n || 0)) + (unit === 'persen' ? '%' : '');
const fmtFull = (n, unit) => (unit === 'rupiah' ? 'Rp ' : '') + nfFull.format(Number(n || 0)) + (unit === 'persen' ? '%' : '');

// Lebar bar dibatasi 100% supaya capaian 300% tak menyeruak keluar kartu;
// angka persennya sendiri tetap ditampilkan apa adanya di sebelahnya.
const barWidth = (p) => Math.min(100, Math.max(0, Number(p || 0))) + '%';

// Warna mengikuti capaian. null (target belum ditetapkan) sengaja abu-abu,
// BUKAN merah — "belum ada target" bukan kegagalan.
const barColor = (p) => {
    if (p === null || p === undefined) return 'bg-slate-300';
    if (p >= 100) return 'bg-emerald-500';
    if (p >= 60) return 'bg-amber-500';
    return 'bg-red-500';
};
const textColor = (p) => {
    if (p === null || p === undefined) return 'text-slate-400';
    if (p >= 100) return 'text-emerald-600';
    if (p >= 60) return 'text-amber-600';
    return 'text-red-600';
};

const gantiKuartal = (key) => router.get('/okr', { q: key }, { preserveScroll: true });

// ---- Grafik tren ----
// Satu metrik ditampilkan sekali waktu; menumpuk view (ratusan ribu) dan omset
// (ratusan juta) di satu sumbu akan membuat salah satunya jadi garis datar.
const trenAktif = ref(0);
const trenPilih = computed(() => props.tren[trenAktif.value] ?? { points: [], label: '', unit: 'angka' });

const lineData = computed(() => ({
    labels: trenPilih.value.points.map((p) => p.label),
    datasets: [
        {
            label: 'Target',
            data: trenPilih.value.points.map((p) => p.target),
            borderColor: '#cbd5e1',
            borderDash: [5, 4],
            pointRadius: 3,
            tension: 0.25,
        },
        {
            label: 'Realisasi',
            data: trenPilih.value.points.map((p) => p.actual),
            borderColor: '#2c4bff',
            backgroundColor: 'rgba(44,75,255,.08)',
            fill: true,
            pointRadius: 3,
            tension: 0.25,
        },
    ],
}));

const lineOpts = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: true, labels: { boxWidth: 12, font: { size: 11 } } },
        tooltip: {
            callbacks: {
                label: (c) => c.dataset.label + ': ' + fmtFull(c.parsed.y, trenPilih.value.unit),
            },
        },
    },
    scales: {
        y: { beginAtZero: true, ticks: { callback: (v) => nfShort.format(v) } },
    },
}));

// ---- Form Objective ----
const objModal = ref(null);          // 'baru' | objek objective yang diedit
const objForm = useForm({ year: 0, quarter: 0, title: '', description: '' });

const bukaObjective = (o = null) => {
    objModal.value = o ?? 'baru';
    // Field top-level (bukan form.data.x) — konvensi useForm di repo ini.
    objForm.year = props.quarter.year;
    objForm.quarter = props.quarter.quarter;
    objForm.title = o?.title ?? '';
    objForm.description = o?.description ?? '';
    objForm.clearErrors();
};

const simpanObjective = () => {
    const tutup = { preserveScroll: true, onSuccess: () => { objModal.value = null; } };
    objModal.value === 'baru'
        ? objForm.post('/okr/objectives', tutup)
        : objForm.put('/okr/objectives/' + objModal.value.id, tutup);
};

const hapusObjective = (o) => {
    if (confirm(`Hapus Objective "${o.title}"? Semua Key Result di dalamnya ikut terhapus.`)) {
        router.delete('/okr/objectives/' + o.id, { preserveScroll: true });
    }
};

// ---- Form Key Result ----
const krModal = ref(null);           // { mode: 'baru'|'edit', objective, kr? }
const krForm = useForm({ objective_id: null, title: '', source: 'manual', metric: '', target: 0, unit: 'angka' });

const bukaKr = (objective, kr = null) => {
    krModal.value = { mode: kr ? 'edit' : 'baru', objective, kr };
    krForm.objective_id = objective.id;
    krForm.title = kr?.title ?? '';
    krForm.source = kr?.source ?? 'manual';
    krForm.metric = kr?.metric ?? '';
    krForm.target = kr?.target ?? 0;
    krForm.unit = kr?.unit ?? 'angka';
    krForm.clearErrors();
};

const simpanKr = () => {
    const tutup = { preserveScroll: true, onSuccess: () => { krModal.value = null; } };
    krModal.value.mode === 'baru'
        ? krForm.post('/okr/key-results', tutup)
        : krForm.put('/okr/key-results/' + krModal.value.kr.id, tutup);
};

const hapusKr = (kr) => {
    if (confirm(`Hapus Key Result "${kr.title}"?`)) {
        router.delete('/okr/key-results/' + kr.id, { preserveScroll: true });
    }
};

// ---- Perbarui realisasi KR manual ----
const aktualModal = ref(null);
const aktualForm = useForm({ actual_manual: 0 });

const bukaAktual = (kr) => {
    aktualModal.value = kr;
    aktualForm.actual_manual = kr.actual;
    aktualForm.clearErrors();
};

const simpanAktual = () => aktualForm.patch('/okr/key-results/' + aktualModal.value.id + '/actual', {
    preserveScroll: true,
    onSuccess: () => { aktualModal.value = null; },
});
</script>

<template>
    <Layout title="OKR">
        <!-- Header: judul + pemilih kuartal. Rentang tanggalnya ditulis eksplisit
             supaya tak ada tebak-tebakan soal batas kuartal. -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">OKR PERUSAHAAN</h1>
                    <p class="text-brand-100 text-sm">{{ quarter.label }} · {{ range.start }} s/d {{ range.end }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <select
                        :value="quarter.key"
                        class="bg-white/15 border border-white/30 rounded-xl px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white/50"
                        @change="gantiKuartal($event.target.value)"
                    >
                        <option v-for="o in quarterOptions" :key="o.key" :value="o.key" class="text-slate-700">{{ o.label }}</option>
                    </select>
                    <button v-if="canManage" class="bg-white text-brand-700 rounded-xl px-3 py-2 text-sm font-semibold hover:bg-brand-50" @click="bukaObjective()">
                        + Objective
                    </button>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-8">
            <!-- Ringkasan: jawaban sekilas sebelum masuk ke rinciannya. -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-400">Progress keseluruhan</p>
                    <p class="text-2xl font-bold text-brand-700 mt-1">{{ ringkasan.progress === null ? '—' : ringkasan.progress + '%' }}</p>
                </div>
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Objective</p><p class="text-2xl font-bold text-slate-700 mt-1">{{ ringkasan.objectives }}</p></div>
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Key Result</p><p class="text-2xl font-bold text-slate-700 mt-1">{{ ringkasan.key_results }}</p></div>
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Tercapai</p><p class="text-2xl font-bold text-emerald-600 mt-1">{{ ringkasan.tercapai }}</p></div>
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Tertinggal</p><p class="text-2xl font-bold text-red-600 mt-1">{{ ringkasan.tertinggal }}</p></div>
            </div>

            <!-- ================= Objective & Key Results ================= -->
            <section class="space-y-4">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-sm uppercase tracking-widest text-slate-400 font-semibold">Objective &amp; Key Results</h2>
                    <p class="text-xs text-slate-400">Realisasi otomatis dari Insight &amp; Pembukuan</p>
                </div>

                <!-- Kuartal kosong. Tawaran salin hanya muncul kalau kuartal lalu
                     memang ada isinya — tombol yang pasti gagal lebih buruk
                     daripada tak ada tombol. -->
                <div v-if="!objectives.length" class="text-center py-16 bg-white border border-brand-100 rounded-2xl">
                    <p class="text-sm text-slate-400">Belum ada Objective untuk {{ quarter.label }}.</p>
                    <button v-if="canManage && bisaSalin"
                            class="mt-3 px-4 py-2 text-sm font-semibold text-brand-700 border border-brand-200 bg-brand-50 hover:bg-brand-100 rounded-xl"
                            @click="salinKuartalLalu">
                        Salin dari {{ kuartalLaluLabel }}
                    </button>
                    <p v-if="canManage && bisaSalin" class="text-[11px] text-slate-400 mt-2">
                        Struktur &amp; targetnya disalin; realisasinya tidak.
                    </p>
                </div>

                <article v-for="o in objectives" :key="o.id" class="bg-white border border-brand-100 rounded-2xl shadow-sm overflow-hidden">
                    <!-- Kepala Objective: judul + progress roll-up -->
                    <div class="p-5 border-b border-slate-100 flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-700">{{ o.title }}</h3>
                            <p v-if="o.description" class="text-xs text-slate-500 mt-1 max-w-prose">{{ o.description }}</p>
                            <div v-if="canManage" class="flex items-center gap-3 mt-2">
                                <button class="text-xs font-semibold text-brand-700 hover:underline" @click="bukaKr(o)">+ Key Result</button>
                                <button class="text-xs font-semibold text-slate-400 hover:text-brand-700" @click="bukaObjective(o)">Ubah</button>
                                <button class="text-xs font-semibold text-slate-400 hover:text-red-600" @click="hapusObjective(o)">Hapus</button>
                            </div>
                        </div>
                        <!-- Progress = rata-rata KR, tiap KR dibatasi 100% dulu.
                             Lihat Objective::progress() untuk alasannya. -->
                        <div class="text-right min-w-[132px]">
                            <b class="text-2xl font-bold text-brand-700">{{ o.progress === null ? '—' : o.progress + '%' }}</b>
                            <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden mt-1.5">
                                <div :class="['h-full rounded-full transition-all', barColor(o.progress)]" :style="{ width: barWidth(o.progress) }"></div>
                            </div>
                        </div>
                    </div>

                    <p v-if="!o.key_results.length" class="px-5 py-6 text-sm text-slate-400">Belum ada Key Result.</p>

                    <!-- Baris Key Result -->
                    <div v-for="kr in o.key_results" :key="kr.id"
                         class="px-5 py-3 border-b border-slate-50 last:border-b-0 grid gap-3 md:grid-cols-[minmax(0,1fr)_200px_140px] md:items-center">
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-slate-700">{{ kr.title }}</span>
                            <span :class="['ml-2 text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full border',
                                           kr.source === 'auto' ? 'bg-brand-50 text-brand-700 border-brand-100'
                                           : kr.source === 'kartu' ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                           : 'bg-amber-50 text-amber-700 border-amber-200']">
                                {{ kr.source_label }}
                            </span>
                            <!-- Penanggung jawab: yang mengejar angka ini. Untuk
                                 sekarang selalu owner & belum bisa dipilih di form. -->
                            <p v-if="kr.owner_name" class="text-[11px] text-slate-400 mt-0.5">PJ: {{ kr.owner_name }}</p>

                            <!-- Langkah-langkah: kartu todolist yang ditautkan ke KR
                                 bersumber 'kartu'. Inilah jembatan goal → papan kerja.
                                 Hanya muncul bila ada tautannya. -->
                            <ul v-if="kr.source === 'kartu' && kr.kartu.length" class="mt-2 space-y-1">
                                <li v-for="k in kr.kartu" :key="k.id" class="flex items-center gap-2 text-[11px]">
                                    <svg v-if="k.selesai" class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    <span v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 flex-shrink-0"></span>
                                    <span :class="k.selesai ? 'text-slate-500 line-through' : 'text-slate-600'">{{ k.judul }}</span>
                                    <span v-if="k.ketepatan === 'terlambat'" class="text-red-600 font-semibold">telat</span>
                                </li>
                            </ul>
                            <p v-else-if="kr.source === 'kartu'" class="text-[11px] text-slate-400 mt-1.5 italic">Belum ada kartu todolist yang ditautkan.</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-1" :title="fmtFull(kr.actual, kr.unit) + ' dari ' + fmtFull(kr.target, kr.unit)">
                                {{ fmt(kr.actual, kr.unit) }} / {{ kr.target > 0 ? fmt(kr.target, kr.unit) : '—' }}
                            </p>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div :class="['h-full rounded-full transition-all', barColor(kr.percent)]" :style="{ width: barWidth(kr.percent) }"></div>
                            </div>
                        </div>
                        <div class="md:text-right">
                            <p :class="['text-sm font-bold', textColor(kr.percent)]">
                                {{ kr.percent === null ? 'tanpa target' : kr.percent + '%' }}
                            </p>
                            <div v-if="canManage" class="flex md:justify-end items-center gap-2.5 mt-0.5">
                                <!-- KR otomatis TIDAK punya tombol ini: angkanya dari
                                     Insight/Pembukuan, dan server pun menolaknya. -->
                                <button v-if="kr.source === 'manual'" class="text-xs font-semibold text-brand-700 hover:underline" @click="bukaAktual(kr)">Perbarui angka</button>
                                <button class="text-xs font-semibold text-slate-400 hover:text-brand-700" @click="bukaKr(o, kr)">Ubah</button>
                                <button class="text-xs font-semibold text-slate-400 hover:text-red-600" @click="hapusKr(kr)">Hapus</button>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <!-- ================= Tren antar kuartal ================= -->
            <section class="space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="text-sm uppercase tracking-widest text-slate-400 font-semibold">Tren 6 kuartal</h2>
                    <!-- Satu metrik sekali waktu: view (ratusan ribu) dan omset
                         (ratusan juta) di satu sumbu akan meratakan salah satunya. -->
                    <div class="flex gap-1.5">
                        <button v-for="(t, i) in tren" :key="t.metric"
                                :class="['text-xs font-semibold rounded-full px-3 py-1.5 border transition',
                                         i === trenAktif ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50']"
                                @click="trenAktif = i">
                            {{ t.label }}
                        </button>
                    </div>
                </div>

                <div class="bg-white border border-brand-100 rounded-2xl shadow-sm p-5 space-y-4">
                    <div class="h-64"><Line :data="lineData" :options="lineOpts" /></div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[420px]">
                            <thead>
                                <tr class="text-left text-[10px] uppercase tracking-widest text-slate-400 border-b border-slate-100">
                                    <th class="py-2 font-semibold">Kuartal</th>
                                    <th class="py-2 font-semibold text-right">Target</th>
                                    <th class="py-2 font-semibold text-right">Realisasi</th>
                                    <th class="py-2 font-semibold text-right">Capaian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in trenPilih.points" :key="p.label" class="border-b border-slate-50 last:border-b-0">
                                    <td class="py-2 text-slate-600">{{ p.label }}</td>
                                    <td class="py-2 text-right tabular-nums text-slate-600">{{ p.target > 0 ? fmtFull(p.target, trenPilih.unit) : '—' }}</td>
                                    <td class="py-2 text-right tabular-nums text-slate-600">{{ fmtFull(p.actual, trenPilih.unit) }}</td>
                                    <td :class="['py-2 text-right tabular-nums font-semibold', textColor(p.percent)]">{{ p.percent === null ? '—' : p.percent + '%' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- ================= Modal Objective ================= -->
        <ModalWrap v-if="objModal" @close="objModal = null">
            <h3 class="text-lg font-bold text-slate-700">{{ objModal === 'baru' ? 'Objective baru' : 'Ubah Objective' }} — {{ quarter.label }}</h3>
            <p class="text-xs text-slate-400 mt-1">Kalimat tujuan; yang terukur adalah Key Result di bawahnya.</p>

            <form class="mt-4 space-y-3" @submit.prevent="simpanObjective">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Objective</label>
                    <input v-model="objForm.title" type="text" placeholder="Jadi rujukan utama konten AI di Indonesia"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
                    <p v-if="objForm.errors.title" class="text-xs text-red-600 mt-1">{{ objForm.errors.title }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Keterangan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea v-model="objForm.description" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300"></textarea>
                    <p v-if="objForm.errors.description" class="text-xs text-red-600 mt-1">{{ objForm.errors.description }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-700" @click="objModal = null">Batal</button>
                    <button type="submit" :disabled="objForm.processing" class="px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-xl disabled:opacity-50">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- ================= Modal Key Result ================= -->
        <ModalWrap v-if="krModal" @close="krModal = null">
            <h3 class="text-lg font-bold text-slate-700">{{ krModal.mode === 'baru' ? 'Key Result baru' : 'Ubah Key Result' }}</h3>
            <p class="text-xs text-slate-400 mt-1">Untuk Objective: {{ krModal.objective.title }}</p>

            <form class="mt-4 space-y-3" @submit.prevent="simpanKr">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Key Result</label>
                    <input v-model="krForm.title" type="text" placeholder="Total view seluruh konten"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
                    <p v-if="krForm.errors.title" class="text-xs text-red-600 mt-1">{{ krForm.errors.title }}</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Sumber angka</label>
                    <select v-model="krForm.source" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                        <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">
                        {{ krForm.source === 'auto'
                            ? 'Realisasi dihitung sendiri dari Insight/Pembukuan dan tidak bisa diisi manual.'
                            : krForm.source === 'kartu'
                            ? 'Realisasi = jumlah kartu todolist yang ditautkan ke KR ini dan sudah selesai. Kartunya dibuat & ditautkan di Kanban.'
                            : 'Realisasi kamu perbarui sendiri lewat tombol “Perbarui angka”.' }}
                    </p>
                </div>

                <!-- Metrik hanya relevan untuk KR otomatis — server pun menuntutnya
                     lewat required_if:source,auto. -->
                <div v-if="krForm.source === 'auto'">
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Metrik</label>
                    <select v-model="krForm.metric" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                        <option value="">— pilih —</option>
                        <option v-for="(label, key) in metrics" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="krForm.errors.metric" class="text-xs text-red-600 mt-1">{{ krForm.errors.metric }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">{{ krForm.source === 'kartu' ? 'Target (jumlah kartu)' : 'Target' }}</label>
                        <input v-model="krForm.target" type="number" min="0" :step="krForm.source === 'kartu' ? 1 : 'any'"
                               class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
                        <p v-if="krForm.errors.target" class="text-xs text-red-600 mt-1">{{ krForm.errors.target }}</p>
                    </div>
                    <!-- KR 'kartu' selalu bersatuan "angka" (menghitung kartu) —
                         server memaksanya, jadi pemilih satuan disembunyikan. -->
                    <div v-if="krForm.source !== 'kartu'">
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Satuan</label>
                        <select v-model="krForm.unit" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <option v-for="(label, key) in units" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-700" @click="krModal = null">Batal</button>
                    <button type="submit" :disabled="krForm.processing" class="px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-xl disabled:opacity-50">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- ================= Modal perbarui realisasi (KR manual) ================= -->
        <ModalWrap v-if="aktualModal" width="max-w-sm" @close="aktualModal = null">
            <h3 class="text-lg font-bold text-slate-700">Perbarui angka</h3>
            <p class="text-xs text-slate-400 mt-1">{{ aktualModal.title }} · target {{ fmtFull(aktualModal.target, aktualModal.unit) }}</p>

            <form class="mt-4 space-y-3" @submit.prevent="simpanAktual">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Realisasi saat ini</label>
                    <input v-model="aktualForm.actual_manual" type="number" min="0" step="any"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
                    <p v-if="aktualForm.errors.actual_manual" class="text-xs text-red-600 mt-1">{{ aktualForm.errors.actual_manual }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-700" @click="aktualModal = null">Batal</button>
                    <button type="submit" :disabled="aktualForm.processing" class="px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-xl disabled:opacity-50">Simpan</button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

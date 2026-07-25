<script setup>
// Dashboard OKR perusahaan per kuartal. Halaman ini = RINGKASAN + NAVIGASI:
// kartu objective diklik → halaman detail (/okr/objectives/{id}) tempat semua
// penyuntingan KR/langkah dilakukan. Di sini tak ada editing KR — hanya "+
// Objective" (dengan divisi) dan pemilih kuartal.
//
// OKR terkunci owner + manager (User::canSee). Realisasi KR `auto` dihitung
// server dari Insight & Pembukuan. Kuartal via ?q=YYYY-Qn.
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import TrendSpark from './okr/TrendSpark.vue';
import { fmt, barWidth, barColor, statusText, buatSpark } from './okr/helpers.js';

const props = defineProps({
    quarter: Object,
    quarterOptions: { type: Array, default: () => [] },
    range: Object,
    objectives: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    tren: { type: Array, default: () => [] },
    metrics: { type: Object, default: () => ({}) },
    sources: { type: Object, default: () => ({}) },
    units: { type: Object, default: () => ({}) },
    kartuTersedia: { type: Array, default: () => [] },
    canManage: Boolean,
    bisaSalin: Boolean,
    kuartalLaluLabel: { type: String, default: '' },
});

const gantiKuartal = (key) => router.get('/okr', { q: key }, { preserveScroll: true });

// Peta menu peran (shared props) — dipakai untuk memutuskan tautan ke Kanban
// boleh ditampilkan atau tidak, pola yang sama dgn kartu modul di Dashboard.
const page = usePage();
const menus = computed(() => page.props.auth?.user?.menus ?? {});

// Kartu ringkasan di atas bertindak sbg TAUTAN ke datanya (pola Dashboard:
// kartu = pintu masuk modul). Bedanya di sini datanya ada di halaman yang sama,
// jadi tautannya anchor + scroll halus, bukan pindah halaman.
const lompatKe = (id) => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });

// ---- Ringkasan turunan ----
// On-track = progress ≥60%, at-risk = <60% (objective yang sudah bisa dinilai).
const onTrack = computed(() => props.objectives.filter((o) => o.progress !== null && o.progress >= 60).length);
const atRisk = computed(() => props.objectives.filter((o) => o.progress !== null && o.progress < 60).length);
const initiatives = computed(() => props.ringkasan.initiatives ?? { total: 0, selesai: 0 });

// Daftar RATA seluruh Key Result kuartal ini (lintas objective) — isi seksi
// "Key Results" yang jadi tujuan kartu ringkasan. Induknya dibawa serta
// (objId/objTitle) supaya tiap baris bisa menaut ke halaman detail objective,
// tempat KR itu benar-benar disunting.
const semuaKr = computed(() => props.objectives.flatMap((o) =>
    o.key_results.map((kr) => ({ ...kr, objId: o.id, objTitle: o.title }))));

// Daftar RATA seluruh langkah (kartu todolist) di bawah KR bersumber 'kartu' —
// inilah angka "Initiatives". Belum selesai ditaruh di atas: yang masih perlu
// dikerjakan lebih layak dilihat duluan daripada yang sudah rampung.
const semuaLangkah = computed(() => {
    const list = semuaKr.value.flatMap((kr) =>
        (kr.kartu ?? []).map((k) => ({ ...k, krTitle: kr.title, objId: kr.objId, objTitle: kr.objTitle })));

    return list.sort((a, b) => Number(a.selesai) - Number(b.selesai));
});

// Kelompokkan objective per divisi (Helicopter View). Tanpa divisi → "Lainnya".
const divisions = computed(() => {
    const peta = {};
    for (const o of props.objectives) {
        const key = o.division || 'Lainnya';
        (peta[key] ??= []).push(o);
    }
    return Object.entries(peta).map(([nama, list]) => {
        const berangka = list.filter((o) => o.progress !== null);
        const avg = berangka.length ? Math.round(berangka.reduce((s, o) => s + o.progress, 0) / berangka.length) : null;
        return { nama, jumlah: list.length, progress: avg };
    });
});
// North Star = objective pertama (urutan position) sebagai objektif penuntun.
const northStar = computed(() => props.objectives[0] ?? null);

// Divisi unik untuk datalist input "+ Objective".
const divisiUnik = computed(() => [...new Set(props.objectives.map((o) => o.division).filter(Boolean))]);

// Badge status kartu objective.
const badge = (p) => {
    if (p === null || p === undefined) return { text: 'Belum dinilai', cls: 'bg-slate-100 text-slate-500 border border-slate-200' };
    if (p >= 60) return { text: 'On Track', cls: 'bg-emerald-50 text-emerald-700 border border-emerald-200' };
    return { text: 'At Risk', cls: 'bg-amber-50 text-amber-700 border border-amber-200' };
};

// Donut OKR Health: conic-gradient dari progress keseluruhan (warna brand).
const donutStyle = computed(() => {
    const p = props.ringkasan.progress ?? 0;
    return { background: `conic-gradient(#2c4bff 0 ${p}%, #e6e9f5 ${p}% 100%)` };
});

// ---- Tren sparkline (ringkas, bawah dashboard) ----
const sparks = computed(() => props.tren.map((t) => {
    const points = t.points ?? [];
    const terakhir = points[points.length - 1] ?? {};
    return {
        metric: t.metric, label: t.label,
        now: fmt(terakhir.actual, t.unit), percent: terakhir.percent ?? null,
        awal: points[0]?.label ?? '', akhir: terakhir.label ?? '',
        geo: buatSpark(points),
    };
}));

// ---- Tambah Objective inline (dashboard) ----
const tambahObjektif = ref(false);
const baru = ref({ title: '', division: '' });
const simpanObjektif = () => {
    if (!baru.value.title.trim()) return;
    router.post('/okr/objectives', {
        year: props.quarter.year, quarter: props.quarter.quarter,
        title: baru.value.title, description: '', division: baru.value.division || null,
    }, {
        preserveScroll: true,
        onSuccess: () => { baru.value = { title: '', division: '' }; tambahObjektif.value = false; },
    });
};
// ---- Ubah & hapus Objective langsung dari kartu dashboard ----
//
//  Dashboard tetap ringkasan, tapi memperbaiki salah ketik judul tak perlu
//  memaksa buka halaman detail dulu. Yang bisa disunting di sini sengaja
//  hanya JUDUL & DIVISI — keterangan panjang tetap di halaman detail, supaya
//  kartu ringkasan tak berubah menjadi formulir.
const editId = ref(null);                        // id objective yang sedang disunting
const editForm = ref({ title: '', division: '' });

const mulaiEdit = (o) => {
    editId.value = o.id;
    editForm.value = { title: o.title, division: o.division ?? '' };
};
const batalEdit = () => { editId.value = null; };

const simpanEdit = (o) => {
    if (!editForm.value.title.trim()) return;
    router.put(`/okr/objectives/${o.id}`, {
        year: props.quarter.year, quarter: props.quarter.quarter,
        title: editForm.value.title,
        division: editForm.value.division || null,
        // `description` sengaja TIDAK dikirim: server hanya menyentuh kolom
        // yang ada di kiriman, jadi keterangan panjang yang ditulis di halaman
        // detail tetap utuh. Mengirimnya "apa adanya" justru berisiko —
        // keterangan yang null akan berubah jadi string kosong.
        // Dikunci OkrTest::test_update_objective_hanya_menyentuh_kolom_yang_dikirim.
    }, { preserveScroll: true, onSuccess: batalEdit });
};

// Key Result ikut terhapus (cascade di skema) — disebutkan di konfirmasi supaya
// tak ada yang kehilangan target sekuartal tanpa sadar.
const hapusObjektif = (o) => {
    if (!confirm(`Hapus Objective "${o.title}"? Semua Key Result di dalamnya ikut terhapus.`)) return;
    router.delete(`/okr/objectives/${o.id}`, { preserveScroll: true });
};

const salinKuartalLalu = () => {
    if (!confirm(`Salin semua Objective & target dari ${props.kuartalLaluLabel} ke ${props.quarter.label}? Realisasinya tidak ikut disalin.`)) return;
    router.post('/okr/salin', { year: props.quarter.year, quarter: props.quarter.quarter }, { preserveScroll: true });
};
</script>

<template>
    <Layout title="OKR">
        <!-- Header brand (konsisten dengan Dashboard/KPI) -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">OKR PERUSAHAAN</h1>
                    <p class="text-brand-100 text-sm">{{ quarter.label }} · {{ range.start }} s/d {{ range.end }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <select :value="quarter.key" @change="gantiKuartal($event.target.value)"
                            class="bg-white/15 border border-white/30 rounded-xl px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white/50">
                        <option v-for="o in quarterOptions" :key="o.key" :value="o.key" class="text-slate-700">{{ o.label }}</option>
                    </select>
                    <button v-if="canManage" class="bg-white text-brand-700 rounded-xl px-3 py-2 text-sm font-semibold hover:bg-brand-50" @click="tambahObjektif = !tambahObjektif">
                        + Objective
                    </button>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Saran divisi dipakai bersama form TAMBAH dan form UBAH kartu,
                 jadi ditaruh di luar keduanya — kalau menempel di salah satu
                 form, saran hilang begitu form itu tertutup. -->
            <datalist v-if="canManage" id="divisi-list"><option v-for="d in divisiUnik" :key="d" :value="d" /></datalist>

            <!-- Form tambah objective (inline) -->
            <form v-if="canManage && tambahObjektif" class="bg-white border border-brand-100 rounded-2xl shadow-sm p-4 flex flex-wrap items-end gap-3" @submit.prevent="simpanObjektif">
                <label class="flex-1 min-w-[220px] text-xs font-semibold text-slate-500">
                    <span class="block mb-1">Objective baru</span>
                    <input v-model="baru.title" type="text" placeholder="Kalimat tujuan…" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-300" />
                </label>
                <label class="text-xs font-semibold text-slate-500">
                    <span class="block mb-1">Divisi</span>
                    <input v-model="baru.division" list="divisi-list" type="text" placeholder="mis. Growth" class="w-40 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-300" />
                </label>
                <button type="submit" class="text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg px-4 py-2">Tambah</button>
                <button type="button" class="text-sm font-semibold text-slate-400 hover:text-slate-600" @click="tambahObjektif = false">Batal</button>
            </form>

            <!-- Stat cards.
                 Tiga kartu terakhir = TAUTAN ke datanya masing-masing (seperti
                 kartu modul di Dashboard): Objectives → daftar kartu objective,
                 Key Results → daftar KR lintas objective, Initiatives → daftar
                 langkah kartu. Overall Progress tetap kartu biasa — angkanya
                 turunan dari ketiganya, tak punya daftar sendiri. -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-brand-100 rounded-2xl p-5 shadow-sm">
                    <p class="text-xs text-slate-400">Overall Progress</p>
                    <p class="text-3xl font-bold text-brand-700 mt-1 tabular-nums">{{ ringkasan.progress === null ? '—' : ringkasan.progress + '%' }}</p>
                    <p class="text-xs text-slate-400 mt-1">rata-rata seluruh objective</p>
                </div>
                <a href="#objectives" @click.prevent="lompatKe('objectives')"
                   class="group block bg-white border border-brand-100 rounded-2xl p-5 shadow-sm hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-400">Active Objectives</p>
                        <span class="text-[11px] font-semibold text-brand-600 whitespace-nowrap">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-800 mt-1 tabular-nums group-hover:text-brand-700 transition">{{ ringkasan.objectives ?? 0 }}</p>
                    <p class="text-xs text-slate-400 mt-1"><span class="text-emerald-600 font-semibold">{{ onTrack }} on track</span> · <span class="text-amber-600 font-semibold">{{ atRisk }} at risk</span></p>
                </a>
                <a href="#key-results" @click.prevent="lompatKe('key-results')"
                   class="group block bg-white border border-brand-100 rounded-2xl p-5 shadow-sm hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-400">Key Results</p>
                        <span class="text-[11px] font-semibold text-brand-600 whitespace-nowrap">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-800 mt-1 tabular-nums group-hover:text-brand-700 transition">{{ ringkasan.key_results ?? 0 }}</p>
                    <p class="text-xs text-slate-400 mt-1"><span class="text-emerald-600 font-semibold">{{ ringkasan.tercapai ?? 0 }} tercapai</span> · {{ ringkasan.tertinggal ?? 0 }} tertinggal</p>
                </a>
                <a href="#initiatives" @click.prevent="lompatKe('initiatives')"
                   class="group block bg-white border border-brand-100 rounded-2xl p-5 shadow-sm hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-400">Initiatives</p>
                        <span class="text-[11px] font-semibold text-brand-600 whitespace-nowrap">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-slate-800 mt-1 tabular-nums group-hover:text-brand-700 transition">{{ initiatives.selesai }}/{{ initiatives.total }}</p>
                    <p class="text-xs text-slate-400 mt-1">langkah kartu selesai</p>
                </a>
            </div>

            <div class="grid lg:grid-cols-[2fr_1fr] gap-6 items-start">
                <!-- Kolom utama: Helicopter + kartu objective -->
                <div class="space-y-6">
                    <!-- Helicopter View -->
                    <section class="bg-white border border-brand-100 rounded-2xl shadow-sm p-5">
                        <div class="flex items-baseline justify-between mb-4">
                            <h2 class="font-bold text-slate-700">Helicopter View</h2>
                            <span class="text-xs text-slate-400">progress per divisi</span>
                        </div>
                        <div v-if="divisions.length" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <div v-for="d in divisions" :key="d.nama" class="border border-slate-100 rounded-xl p-3 bg-slate-50/60">
                                <p class="text-[11px] text-slate-400 truncate">{{ d.nama }}</p>
                                <p class="text-xl font-bold text-slate-800 mt-1 tabular-nums">{{ d.progress === null ? '—' : d.progress + '%' }}</p>
                                <p class="text-[11px] text-slate-400">{{ d.jumlah }} objective</p>
                            </div>
                        </div>
                        <!-- North Star -->
                        <div v-if="northStar" class="rounded-xl border border-brand-100 p-4 bg-gradient-to-br from-brand-50 to-white">
                            <p class="text-[11px] uppercase tracking-widest text-brand-600 font-bold">North Star Objective</p>
                            <p class="text-slate-800 font-semibold mt-1">{{ northStar.title }}</p>
                            <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden mt-2">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" :style="{ width: barWidth(ringkasan.progress) }"></div>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Company-level progress: {{ ringkasan.progress === null ? '—' : ringkasan.progress + '%' }}</p>
                        </div>
                    </section>

                    <!-- Kartu Objective (dapat diklik → detail).
                         id="objectives" = tujuan kartu ringkasan "Active Objectives";
                         scroll-mt-4 memberi jeda supaya judul seksi tak mepet tepi atas. -->
                    <section id="objectives" class="scroll-mt-4">
                        <div class="flex items-baseline justify-between mb-3">
                            <h2 class="text-sm uppercase tracking-widest text-slate-400 font-semibold">Objectives</h2>
                            <span class="text-xs text-slate-400">klik kartu untuk detail &amp; edit</span>
                        </div>

                        <div v-if="!objectives.length" class="text-center py-14 bg-white border border-brand-100 rounded-2xl">
                            <p class="text-sm text-slate-400">Belum ada Objective untuk {{ quarter.label }}.</p>
                            <button v-if="canManage && bisaSalin" class="mt-3 px-4 py-2 text-sm font-semibold text-brand-700 border border-brand-200 bg-brand-50 hover:bg-brand-100 rounded-xl" @click="salinKuartalLalu">
                                Salin dari {{ kuartalLaluLabel }}
                            </button>
                        </div>

                        <div v-else class="grid sm:grid-cols-2 gap-4">
                            <template v-for="o in objectives" :key="o.id">
                                <!-- Mode UBAH: kartu berganti jadi formulir.
                                     Bukan formulir di DALAM kartu, karena kartunya
                                     sendiri adalah <Link> — form & tombol di dalam
                                     tautan akan saling merebut klik. -->
                                <form v-if="editId === o.id" class="bg-white border border-brand-300 rounded-2xl shadow-sm p-5 space-y-3" @submit.prevent="simpanEdit(o)">
                                    <label class="block text-xs font-semibold text-slate-500">
                                        <span class="block mb-1">Judul objective</span>
                                        <input v-model="editForm.title" type="text" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-300" />
                                    </label>
                                    <label class="block text-xs font-semibold text-slate-500">
                                        <span class="block mb-1">Divisi</span>
                                        <input v-model="editForm.division" list="divisi-list" type="text" placeholder="mis. Growth" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-300" />
                                    </label>
                                    <p class="text-[11px] text-slate-400">Keterangan &amp; Key Result disunting di halaman detail.</p>
                                    <div class="flex items-center gap-3">
                                        <button type="submit" class="text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg px-4 py-1.5">Simpan</button>
                                        <button type="button" class="text-sm font-semibold text-slate-400 hover:text-slate-600" @click="batalEdit">Batal</button>
                                    </div>
                                </form>

                                <!-- Mode BACA: kartu tautan ke detail + baris aksi -->
                                <Link v-else :href="`/okr/objectives/${o.id}?q=${quarter.key}`"
                                      class="group bg-white border border-brand-100 rounded-2xl shadow-sm p-5 hover:border-brand-300 hover:shadow-md transition block">
                                    <div class="flex items-start justify-between gap-3">
                                        <h3 class="font-bold text-slate-700 leading-snug group-hover:text-brand-700 transition">{{ o.title }}</h3>
                                        <span :class="['text-[11px] font-bold px-2 py-1 rounded-full whitespace-nowrap', badge(o.progress).cls]">{{ badge(o.progress).text }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-2 text-xs text-slate-400">
                                        <span v-if="o.division" class="px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 font-semibold">{{ o.division }}</span>
                                        <span v-if="o.created_by_name">{{ o.created_by_name }}</span>
                                        <span>· {{ o.key_results.length }} KR</span>
                                    </div>
                                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden mt-3">
                                        <div :class="['h-full rounded-full', barColor(o.progress)]" :style="{ width: barWidth(o.progress) }"></div>
                                    </div>
                                    <div class="flex items-center justify-between mt-1.5">
                                        <span class="text-xs text-slate-400">Overall progress</span>
                                        <span class="text-sm font-bold text-slate-700 tabular-nums">{{ o.progress === null ? '—' : o.progress + '%' }}</span>
                                    </div>
                                    <!-- Aksi kartu. prevent+stop wajib: tanpanya klik
                                         tombol ikut memicu navigasi <Link> induknya. -->
                                    <div v-if="canManage" class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-100">
                                        <button type="button" class="text-xs font-semibold text-slate-400 hover:text-brand-700" @click.prevent.stop="mulaiEdit(o)">Ubah</button>
                                        <button type="button" class="text-xs font-semibold text-slate-400 hover:text-red-600" @click.prevent.stop="hapusObjektif(o)">Hapus</button>
                                        <span class="ml-auto text-xs font-semibold text-brand-600">Detail →</span>
                                    </div>
                                </Link>
                            </template>
                        </div>
                    </section>

                    <!-- Key Results — daftar rata lintas objective.
                         Tujuan kartu ringkasan "Key Results". Baris di sini
                         hanya BACA: klik membuka objective induknya, tempat KR
                         disunting (dashboard = ringkasan + navigasi). -->
                    <section id="key-results" class="scroll-mt-4">
                        <div class="flex items-baseline justify-between mb-3">
                            <h2 class="text-sm uppercase tracking-widest text-slate-400 font-semibold">Key Results</h2>
                            <span class="text-xs text-slate-400">{{ semuaKr.length }} KR · klik untuk buka objective-nya</span>
                        </div>

                        <div v-if="semuaKr.length" class="bg-white border border-brand-100 rounded-2xl shadow-sm divide-y divide-slate-100 overflow-hidden">
                            <Link v-for="kr in semuaKr" :key="kr.id" :href="`/okr/objectives/${kr.objId}?q=${quarter.key}`"
                                  class="group flex items-center gap-4 px-5 py-3 hover:bg-brand-50/60 transition">
                                <!-- Judul KR + induk & sumber angkanya -->
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-700 truncate group-hover:text-brand-700 transition">{{ kr.title }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ kr.objTitle }} · {{ kr.source_label }}</p>
                                </div>
                                <!-- Meter capaian + realisasi/target (disembunyikan di layar sempit) -->
                                <div class="w-32 shrink-0 hidden sm:block">
                                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div :class="['h-full rounded-full', barColor(kr.percent)]" :style="{ width: barWidth(kr.percent) }"></div>
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1 text-right tabular-nums">{{ fmt(kr.actual, kr.unit) }} / {{ fmt(kr.target, kr.unit) }}</p>
                                </div>
                                <span :class="['text-sm font-bold tabular-nums w-14 text-right shrink-0', statusText(kr.percent)]">
                                    {{ kr.percent === null ? '—' : kr.percent + '%' }}
                                </span>
                            </Link>
                        </div>
                        <p v-else class="text-center py-10 bg-white border border-brand-100 rounded-2xl text-sm text-slate-400">
                            Belum ada Key Result di kuartal ini.
                        </p>
                    </section>

                    <!-- Initiatives — langkah (kartu todolist) di bawah KR bersumber 'kartu'.
                         Tujuan kartu ringkasan "Initiatives". Kartunya hidup di
                         Kanban todolist, jadi header seksi menautkan ke papan itu
                         (hanya bila perannya memang boleh melihat Kanban). -->
                    <section id="initiatives" class="scroll-mt-4">
                        <div class="flex items-baseline justify-between mb-3 gap-3">
                            <h2 class="text-sm uppercase tracking-widest text-slate-400 font-semibold">Initiatives</h2>
                            <Link v-if="menus.kanban" href="/pipelines/kanban?category=todolist"
                                  class="text-xs font-semibold text-brand-600 hover:text-brand-800 whitespace-nowrap">
                                Buka Kanban todolist →
                            </Link>
                        </div>

                        <div v-if="semuaLangkah.length" class="bg-white border border-brand-100 rounded-2xl shadow-sm divide-y divide-slate-100 overflow-hidden">
                            <Link v-for="k in semuaLangkah" :key="k.id" :href="`/okr/objectives/${k.objId}?q=${quarter.key}`"
                                  class="group flex items-center gap-3 px-5 py-2.5 hover:bg-brand-50/60 transition">
                                <!-- Penanda selesai: centang hijau vs lingkaran kosong -->
                                <svg v-if="k.selesai" class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span v-else class="w-4 h-4 rounded-full border border-slate-300 shrink-0"></span>
                                <div class="min-w-0 flex-1">
                                    <p :class="['text-sm truncate', k.selesai ? 'text-slate-400 line-through' : 'text-slate-700 group-hover:text-brand-700 transition']">{{ k.judul }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ k.krTitle }} · {{ k.objTitle }}</p>
                                </div>
                                <!-- Penanda waktu: hanya yang perlu perhatian (telat/lewat deadline) -->
                                <span v-if="k.ketepatan === 'terlambat'" class="text-[11px] font-bold text-red-700 whitespace-nowrap shrink-0">telat</span>
                                <span v-else-if="k.ketepatan === 'lewat'" class="text-[11px] font-bold text-amber-700 whitespace-nowrap shrink-0">lewat deadline</span>
                            </Link>
                        </div>
                        <p v-else class="text-center py-10 bg-white border border-brand-100 rounded-2xl text-sm text-slate-400">
                            Belum ada langkah kartu untuk Key Result kuartal ini.
                        </p>
                    </section>
                </div>

                <!-- Kolom samping: OKR Health -->
                <div class="space-y-6">
                    <section class="bg-white border border-brand-100 rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-slate-700 mb-4">OKR Health</h2>
                        <div class="flex items-center gap-5">
                            <div class="relative w-32 h-32 rounded-full grid place-items-center shrink-0" :style="donutStyle">
                                <div class="w-[86px] h-[86px] rounded-full bg-white grid place-items-center">
                                    <span class="text-2xl font-bold text-slate-800 tabular-nums">{{ ringkasan.progress === null ? '—' : ringkasan.progress + '%' }}</span>
                                </div>
                            </div>
                            <div class="text-sm text-slate-500 space-y-1.5">
                                <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-brand-600"></span>Overall: {{ ringkasan.progress === null ? '—' : ringkasan.progress + '%' }}</div>
                                <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>On Track: {{ onTrack }}</div>
                                <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>At Risk: {{ atRisk }}</div>
                            </div>
                        </div>
                    </section>

                    <!-- Tren ringkas -->
                    <section v-if="sparks.length" class="bg-white border border-brand-100 rounded-2xl shadow-sm p-5">
                        <div class="flex items-baseline justify-between mb-3">
                            <h2 class="font-bold text-slate-700">Tren 6 Kuartal</h2>
                            <span class="text-xs text-slate-400">realisasi vs target</span>
                        </div>
                        <div class="space-y-4">
                            <TrendSpark v-for="s in sparks" :key="s.metric" :spark="s" />
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </Layout>
</template>

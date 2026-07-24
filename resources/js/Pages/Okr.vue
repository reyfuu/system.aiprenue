<script setup>
// Halaman OKR perusahaan per kuartal: Objective berisi Key Result.
//
// Realisasi KR bertipe `auto` DIHITUNG server dari modul Insight & Pembukuan —
// tak ada angka realisasi otomatis yang diketik manusia. KR `manual` untuk
// target tanpa sumber data; KR `kartu` realisasinya dari kartu todolist selesai.
//
// Halaman ini terkunci owner + manager (semua peran yang melihatnya bisa
// mengelola) → seluruh penyuntingan dibuat INLINE, tanpa modal: klik judul/angka
// untuk mengubah, tambah KR/langkah langsung di tempat. Orkestrator ini hanya
// menata seksi & meneruskan data ke komponen di Pages/okr/*.
//
// Kuartal dipilih lewat querystring ?q=YYYY-Qn supaya tautannya bisa dibagikan.
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import ObjectiveBlock from './okr/ObjectiveBlock.vue';
import TrendSpark from './okr/TrendSpark.vue';
import { fmt, buatSpark } from './okr/helpers.js';

const props = defineProps({
    quarter: Object,          // { year, quarter, key, label }
    quarterOptions: { type: Array, default: () => [] },
    range: Object,            // { start, end }
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

// Salin Objective + target kuartal lalu — dikonfirmasi (menulis banyak baris).
const salinKuartalLalu = () => {
    if (!confirm(`Salin semua Objective & target dari ${props.kuartalLaluLabel} ke ${props.quarter.label}? Realisasinya tidak ikut disalin.`)) return;
    router.post('/okr/salin', { year: props.quarter.year, quarter: props.quarter.quarter }, { preserveScroll: true });
};

// Tambah Objective inline (pengganti modal): satu input judul di bawah daftar.
const tambahObjektif = ref(false);
const judulBaru = ref('');
const simpanObjektif = () => {
    if (!judulBaru.value.trim()) return;
    router.post('/okr/objectives', { year: props.quarter.year, quarter: props.quarter.quarter, title: judulBaru.value, description: '' }, {
        preserveScroll: true,
        onSuccess: () => { judulBaru.value = ''; tambahObjektif.value = false; },
    });
};

// ---- Ringkasan eksekutif (data-driven) ----
const hariTersisa = computed(() => {
    if (!props.range?.end) return 0;
    const akhir = new Date(props.range.end + 'T23:59:59');
    const selisih = Math.ceil((akhir - new Date()) / 86400000);
    return selisih > 0 ? selisih : 0;
});
const semuaKr = computed(() => props.objectives.flatMap((o) => o.key_results ?? []));
const krTerkuat = computed(() => {
    const berangka = semuaKr.value.filter((k) => k.percent !== null && k.percent !== undefined);
    return berangka.length ? berangka.reduce((a, b) => (b.percent > a.percent ? b : a)) : null;
});
const krBerisiko = computed(() => {
    const rawan = semuaKr.value.filter((k) => k.percent !== null && k.percent !== undefined && k.percent < 60);
    return rawan.length ? rawan.reduce((a, b) => (b.percent < a.percent ? b : a)) : null;
});
const objDiJalur = computed(() => props.objectives.filter((o) => o.progress !== null && o.progress >= 60).length);

// ---- Sparkline tren ----
const sparks = computed(() => props.tren.map((t) => {
    const points = t.points ?? [];
    const terakhir = points[points.length - 1] ?? {};
    return {
        metric: t.metric,
        label: t.label,
        now: fmt(terakhir.actual, t.unit),
        percent: terakhir.percent ?? null,
        awal: points[0]?.label ?? '',
        akhir: terakhir.label ?? '',
        geo: buatSpark(points),
    };
}));
</script>

<template>
    <Layout title="OKR">
        <!-- Lembar laporan: kolom tunggal terpusat, berkesan kertas. Serif untuk
             display & naratif; angka/label meng-override ke font-sans. -->
        <div class="font-serif max-w-[860px] mx-auto bg-white md:border-x border-slate-200 min-h-screen px-5 sm:px-10 lg:px-14 text-slate-700">

            <!-- ================= Masthead ================= -->
            <header class="flex flex-wrap items-baseline justify-between gap-3 pt-8 pb-3 border-b-2 border-slate-800">
                <div class="font-sans font-extrabold tracking-[0.22em] text-xs uppercase text-slate-800">OKR · System AI Preneur</div>
                <div class="font-sans text-[11px] tracking-wider uppercase text-slate-400">Laporan {{ quarter.label }}</div>
            </header>

            <!-- Kontrol: pemilih kuartal -->
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-slate-100">
                <label class="font-sans inline-flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-slate-400">Periode</span>
                    <select :value="quarter.key" @change="gantiKuartal($event.target.value)"
                            class="font-semibold text-slate-700 border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                        <option v-for="o in quarterOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                    </select>
                    <span class="text-slate-400 text-xs">· {{ range.start }} – {{ range.end }}</span>
                </label>
            </div>

            <!-- ================= Sampul-tesis ================= -->
            <section class="py-9 sm:py-12 border-b border-slate-100">
                <p class="font-sans uppercase tracking-[0.2em] text-[11px] font-bold text-brand-600 mb-6">
                    Progress perusahaan · {{ range.start }} – {{ range.end }}
                </p>
                <div class="flex flex-col sm:flex-row sm:items-end gap-6 sm:gap-12">
                    <div class="leading-none shrink-0">
                        <div class="font-semibold text-slate-800 tracking-tight text-[86px] sm:text-[128px] leading-[0.82]">
                            <span class="tabular-nums">{{ ringkasan.progress === null ? '—' : ringkasan.progress }}</span><span class="text-3xl text-slate-400">%</span>
                        </div>
                        <div class="font-sans uppercase tracking-widest text-[11px] text-slate-400 font-semibold mt-3">Rata-rata pencapaian objective</div>
                        <div class="h-[3px] w-16 bg-brand-600 mt-4"></div>
                    </div>
                    <p class="text-xl sm:text-2xl leading-snug text-slate-800 max-w-[30ch] text-balance">
                        <template v-if="krTerkuat && krBerisiko">
                            <b class="text-emerald-700 font-semibold">{{ krTerkuat.title }}</b> memimpin, tetapi <b class="text-red-700 font-semibold">{{ krBerisiko.title.toLowerCase() }}</b> tertinggal dari sasaran kuartal.
                        </template>
                        <template v-else-if="krTerkuat">
                            Semua Key Result di jalur — dipimpin <b class="text-emerald-700 font-semibold">{{ krTerkuat.title }}</b>.
                        </template>
                        <template v-else>
                            Belum ada Key Result terukur untuk kuartal ini.
                        </template>
                    </p>
                </div>

                <!-- Scoreboard -->
                <div class="font-sans flex flex-wrap mt-8 sm:mt-10 border-t border-slate-100">
                    <div class="flex-1 min-w-[100px] border-r border-slate-100 pr-5 py-4">
                        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ ringkasan.objectives ?? 0 }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">Objective</div>
                    </div>
                    <div class="flex-1 min-w-[100px] border-r border-slate-100 px-5 py-4">
                        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ ringkasan.key_results ?? 0 }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">Key Result</div>
                    </div>
                    <div class="flex-1 min-w-[100px] border-r border-slate-100 px-5 py-4">
                        <div class="text-2xl font-bold text-emerald-700 tabular-nums">{{ ringkasan.tercapai ?? 0 }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">Tercapai ≥100%</div>
                    </div>
                    <div class="flex-1 min-w-[100px] border-r border-slate-100 px-5 py-4">
                        <div class="text-2xl font-bold text-red-700 tabular-nums">{{ ringkasan.tertinggal ?? 0 }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">Berisiko &lt;60%</div>
                    </div>
                    <div class="flex-1 min-w-[100px] px-5 py-4">
                        <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ hariTersisa }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">Hari tersisa</div>
                    </div>
                </div>
            </section>

            <!-- ================= Ringkasan eksekutif ================= -->
            <section v-if="objectives.length" class="py-8 sm:py-9 border-b border-slate-100 grid sm:grid-cols-[130px_1fr] gap-4 sm:gap-10">
                <div class="font-sans text-[11px] uppercase tracking-widest text-slate-400 font-semibold sm:pt-1">Ringkasan<br class="hidden sm:block"> Eksekutif</div>
                <div class="text-lg sm:text-xl leading-relaxed text-slate-700 max-w-[62ch]">
                    <p class="first-letter:float-left first-letter:font-serif first-letter:text-6xl first-letter:leading-[0.7] first-letter:pr-3 first-letter:pt-1 first-letter:font-semibold first-letter:text-slate-800">
                        <b class="tabular-nums text-slate-800">{{ objDiJalur }}</b> dari <b class="tabular-nums text-slate-800">{{ ringkasan.objectives }}</b> objective berjalan di jalur untuk {{ quarter.label }}.
                        <template v-if="krTerkuat">
                            Metrik terkuat <em class="not-italic font-semibold text-emerald-700 border-b-2 border-emerald-200">{{ krTerkuat.title }}</em> di {{ krTerkuat.percent }}%.
                        </template>
                        <template v-if="krBerisiko">
                            Yang perlu didorong: <u class="no-underline font-semibold text-red-700 border-b-2 border-red-200">{{ krBerisiko.title }}</u> baru {{ krBerisiko.percent }}%, sisa {{ hariTersisa }} hari.
                        </template>
                        <template v-else>
                            Tak ada Key Result yang berisiko.
                        </template>
                    </p>
                </div>
            </section>

            <!-- ================= Objective & Key Result ================= -->
            <div class="font-sans text-[11px] uppercase tracking-widest text-slate-400 font-semibold pt-9 pb-1">Objective &amp; Key Result</div>

            <!-- Kuartal kosong -->
            <div v-if="!objectives.length" class="text-center py-16 border-y border-slate-100 mt-2">
                <p class="text-slate-400">Belum ada Objective untuk {{ quarter.label }}.</p>
                <button v-if="canManage && bisaSalin"
                        class="font-sans mt-4 px-4 py-2 text-sm font-semibold text-brand-700 border border-brand-200 bg-brand-50 hover:bg-brand-100 rounded-xl"
                        @click="salinKuartalLalu">
                    Salin dari {{ kuartalLaluLabel }}
                </button>
                <p v-if="canManage && bisaSalin" class="font-sans text-[11px] text-slate-400 mt-2">Struktur &amp; targetnya disalin; realisasinya tidak.</p>
            </div>

            <!-- Objective bernomor 01, 02, 03 … -->
            <ObjectiveBlock v-for="(o, oi) in objectives" :key="o.id"
                            :objective="o" :index="oi" :quarter="quarter" :can-manage="canManage"
                            :kartu-tersedia="kartuTersedia" :sources="sources" :metrics="metrics" :units="units" />

            <!-- Tambah Objective inline -->
            <div v-if="canManage && objectives.length" class="py-6 border-b border-slate-100">
                <form v-if="tambahObjektif" class="font-sans flex flex-wrap items-center gap-2" @submit.prevent="simpanObjektif">
                    <input v-model="judulBaru" type="text" placeholder="Judul Objective baru…" autofocus
                           class="flex-1 min-w-[220px] border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
                    <button type="submit" class="text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg px-3 py-1.5">Tambah</button>
                    <button type="button" class="text-sm font-semibold text-slate-400 hover:text-slate-600" @click="tambahObjektif = false; judulBaru = ''">Batal</button>
                </form>
                <button v-else type="button" class="font-sans text-sm font-semibold text-brand-700 hover:underline" @click="tambahObjektif = true">
                    + Objective
                </button>
            </div>

            <!-- ================= Lampiran · Tren 6 kuartal ================= -->
            <section v-if="sparks.length" class="py-9">
                <div class="font-sans text-[11px] uppercase tracking-widest text-slate-400 font-semibold pb-1">Lampiran · Tren Enam Kuartal</div>
                <div class="font-sans flex gap-5 text-[12px] text-slate-500 mb-5">
                    <span class="inline-flex items-center gap-2"><span class="w-4 h-[2.5px] bg-brand-600"></span>Realisasi</span>
                    <span class="inline-flex items-center gap-2"><span class="w-4 border-t-2 border-dashed border-slate-400"></span>Target</span>
                </div>
                <div class="grid sm:grid-cols-3 gap-x-8 gap-y-7">
                    <TrendSpark v-for="s in sparks" :key="s.metric" :spark="s" />
                </div>
            </section>

            <!-- Kolofon -->
            <div class="font-sans text-center text-xs text-slate-400 border-t-2 border-slate-800 py-6">
                OKR Perusahaan · {{ quarter.label }} · System AI Preneur
            </div>
        </div>
    </Layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import ModalWrap from '../ModalWrap.vue';

const props = defineProps({
    quarter: Object, // { year, quarter, key, label }
    quarterOptions: { type: Array, default: () => [] },
    range: Object, // { start, end } — rentang tanggal kuartal
    objectives: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
    sources: { type: Object, default: () => ({}) },
    units: { type: Object, default: () => ({}) },
    priorities: { type: Array, default: () => [] },
    kartuTersedia: { type: Array, default: () => [] },
    kanbanBoards: { type: Array, default: () => [] },
    kanbanColumns: { type: Object, default: () => ({}) },
    cardCategories: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    canManage: Boolean,
    bisaSalin: Boolean,
    kuartalLaluLabel: { type: String, default: '' },
    okrTitle: { type: String, default: '' }, // judul OKR perusahaan (bebas per kuartal)
});

// Mode tampilan: 'detail' (rincian OKR langsung) atau 'landing' (overview landing)
const viewMode = ref('detail');

// ---- Edit judul OKR perusahaan (bebas per kuartal) ----
const editJudul = ref(false); // true = tampil input inline
const judulForm = useForm({ title: props.okrTitle, q: props.quarter.key });
const mulaiEditJudul = () => {
    judulForm.title = props.okrTitle; // selalu mulai dari judul kuartal aktif
    judulForm.q = props.quarter.key;
    editJudul.value = true;
};
const simpanJudul = () => {
    if (!judulForm.title.trim()) return;
    judulForm.put('/okr/title', { preserveScroll: true, onSuccess: () => { editJudul.value = false; } });
};


// Total tugas & tugas selesai
const totalTugas = computed(() => {
    return props.objectives.reduce((total, o) => {
        return total + o.key_results.reduce((krTotal, kr) => krTotal + (kr.kartu ? kr.kartu.length : 0), 0);
    }, 0);
});

const tugasSelesai = computed(() => {
    return props.objectives.reduce((total, o) => {
        return (
            total +
            o.key_results.reduce((krTotal, kr) => {
                return krTotal + (kr.kartu ? kr.kartu.filter((k) => k.selesai).length : 0);
            }, 0)
        );
    }, 0);
});

const persenTugasSelesai = computed(() => {
    return totalTugas.value > 0 ? Math.round((tugasSelesai.value / totalTugas.value) * 100) : 0;
});

const nfFull = new Intl.NumberFormat('id-ID');
const rpShort = (n) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(n || 0);
const fmtFull = (n, unit) => (unit === 'rupiah' ? 'Rp' : '') + nfFull.format(Number(n || 0)) + (unit === 'persen' ? '%' : '');

const gantiKuartal = (key) => router.get('/okr', { q: key }, { preserveScroll: true });

const salinKuartalLalu = () => {
    if (
        !confirm(
            `Salin semua Objective & target dari ${props.kuartalLaluLabel} ke ${props.quarter.label}? Realisasinya tidak ikut disalin.`,
        )
    )
        return;
    router.post('/okr/salin', { year: props.quarter.year, quarter: props.quarter.quarter }, { preserveScroll: true });
};

// ---- Form Objective ----
const objModal = ref(null);
const objForm = useForm({
    year: 0,
    quarter: 0,
    title: '',
    description: '',
    priority_name: '',
    omset_target: '',
    omset_owner_id: '',
});

const bukaObjective = (o = null) => {
    objModal.value = o ?? 'baru';
    objForm.year = props.quarter.year;
    objForm.quarter = props.quarter.quarter;
    objForm.title = o?.title ?? '';
    objForm.description = o?.description ?? '';
    objForm.priority_name = o?.priority?.name ?? '';
    objForm.omset_target = o?.omset_target || '';
    objForm.omset_owner_id = o?.omset_owner_id ?? '';
    objForm.clearErrors();
};

const simpanObjective = () => {
    const tutup = {
        preserveScroll: true,
        onSuccess: () => {
            objModal.value = null;
        },
    };
    objModal.value === 'baru' ? objForm.post('/okr/objectives', tutup) : objForm.put('/okr/objectives/' + objModal.value.id, tutup);
};

const hapusObjective = (o) => {
    if (confirm(`Hapus Objective "${o.title}"? Semua Key Result di dalamnya ikut terhapus.`)) {
        router.delete('/okr/objectives/' + o.id, { preserveScroll: true });
    }
};

// ---- Form Key Result ----
const krModal = ref(null);
const krForm = useForm({
    objective_id: null,
    title: '',
    description: '',
    source: 'kartu',
    board_key: '',
    metric: '',
    target: 0,
    unit: 'angka',
    priority_name: '',
    kanban_board_key: '',
    kanban_column_key: '',
    card_category: '',
    card_description: '',
    assigned_to: '',
    deadline: '',
});
const executionColumns = computed(() => props.kanbanColumns[krForm.kanban_board_key] ?? []);
const showWorkstream = ref(false);

const sourceOptions = [
    {
        value: 'kartu',
        label: 'Kartu',
        desc: 'Realisasi = kartu Kanban yang selesai',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    },
];

const unitOptions = [
    { value: 'angka', label: 'Angka / Jumlah' },
    { value: 'rupiah', label: 'Rupiah (Rp)' },
    { value: 'persen', label: 'Persentase (%)' },
];

watch(
    () => krForm.kanban_board_key,
    () => {
        if (!executionColumns.value.some((column) => column.key === krForm.kanban_column_key)) {
            krForm.kanban_column_key = executionColumns.value[0]?.key ?? '';
        }
    },
);

const bukaKr = (objective, kr = null) => {
    krModal.value = { mode: kr ? 'edit' : 'baru', objective, kr };
    krForm.objective_id = objective.id;
    krForm.title = kr?.title ?? '';
    krForm.description = kr?.description ?? '';
    krForm.source = 'kartu';
    krForm.board_key = kr?.board_key ?? '';
    krForm.metric = kr?.metric ?? '';
    krForm.target = kr?.target ?? 0;
    krForm.unit = kr?.unit ?? 'angka';
    krForm.priority_name = kr?.priority?.name ?? '';
    krForm.kanban_board_key = kr?.kartu?.find((k) => k.is_master)?.board ?? props.kanbanBoards[0]?.key ?? '';
    krForm.kanban_column_key = (props.kanbanColumns[krForm.kanban_board_key] ?? [])[0]?.key ?? '';
    krForm.card_category = '';
    krForm.card_description = '';
    const master = kr?.kartu?.find((k) => k.is_master) ?? null;
    krForm.assigned_to = master?.assigned_to ?? '';
    krForm.deadline = master?.deadline ?? '';
    showWorkstream.value = krModal.value.mode === 'baru';
    krForm.clearErrors();
};

const simpanKr = () => {
    const tutup = {
        preserveScroll: true,
        onSuccess: () => {
            krModal.value = null;
        },
    };
    krModal.value.mode === 'baru' ? krForm.post('/okr/key-results', tutup) : krForm.put('/okr/key-results/' + krModal.value.kr.id, tutup);
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

const simpanAktual = () =>
    aktualForm.patch('/okr/key-results/' + aktualModal.value.id + '/actual', {
        preserveScroll: true,
        onSuccess: () => {
            aktualModal.value = null;
        },
    });
</script>

<template>
    <Layout title="OKR">

        <!-- =========================================================================
             LANDING OVERVIEW VIEW (Tampilan Awal Sebelum Masuk ke OKR Detail)
             ========================================================================= -->
        <div v-if="viewMode === 'landing'" class="min-h-screen bg-slate-50 flex flex-col justify-between">
            <!-- Header Topbar Landing -->
            <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between shadow-2xs">
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">OKR — Target &amp; Eksekusi Tim</h1>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Aipreneur</span>
            </header>

            <!-- Main Body Landing -->
            <main class="p-8 max-w-7xl w-full mx-auto flex-1 space-y-6">

                <!-- OKR Card Container (Sesuai Gambar Screenshot 100%) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
                    <div
                        class="bg-white border border-slate-200/90 hover:border-blue-400 hover:shadow-md transition-all rounded-2xl p-6 cursor-pointer space-y-5 group"
                        @click="viewMode = 'detail'"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <!-- Judul OKR: tampil biasa + pensil (canManage). Klik pensil → input inline.
                                     @click.stop di kontrol edit supaya tidak ikut membuka detail (kartu clickable). -->
                                <h2
                                    v-if="!editJudul"
                                    class="text-base font-extrabold text-slate-900 group-hover:text-blue-600 transition-colors leading-snug"
                                >
                                    {{ okrTitle }}
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="ml-1 align-middle text-slate-300 hover:text-blue-600"
                                        title="Edit judul OKR"
                                        @click.stop="mulaiEditJudul"
                                    >
                                        <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                </h2>
                                <div v-else class="flex items-center gap-1.5" @click.stop>
                                    <input
                                        v-model="judulForm.title"
                                        type="text"
                                        maxlength="255"
                                        class="flex-1 min-w-0 text-sm font-bold text-slate-800 border border-blue-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        @keyup.enter="simpanJudul"
                                        @keyup.esc="editJudul = false"
                                    />
                                    <button
                                        type="button"
                                        class="text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-60 rounded-lg px-2 py-1"
                                        :disabled="judulForm.processing"
                                        @click.stop="simpanJudul"
                                    >
                                        Simpan
                                    </button>
                                    <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700 px-1" @click.stop="editJudul = false">Batal</button>
                                </div>
                            </div>
                            <span
                                class="shrink-0 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-700 uppercase tracking-wider"
                            >
                                AKTIF
                            </span>
                        </div>

                        <p class="text-xs font-semibold text-slate-400">{{ quarter.label }} · Perusahaan</p>

                        <!-- Progress Task -->
                        <div class="space-y-1.5 pt-1">
                            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                                <span>{{ tugasSelesai }}/{{ totalTugas }} tugas selesai</span>
                                <span class="text-slate-900">{{ persenTugasSelesai }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div
                                    class="bg-slate-200 h-full rounded-full transition-all"
                                    :style="{ width: persenTugasSelesai + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Objective & Date Footer -->
                        <div class="pt-2 space-y-2 border-t border-slate-100">
                            <p class="text-xs font-semibold text-slate-400">
                                {{ objectives.length }} Objective · {{ range.start }}–{{ range.end }}
                            </p>

                            <!-- Role Badges -->
                            <div class="flex items-center gap-1.5">
                                <span class="text-[9px] font-extrabold px-2 py-0.5 rounded bg-slate-100 text-slate-600 uppercase">CMO</span>
                                <span class="text-[9px] font-extrabold px-2 py-0.5 rounded bg-slate-100 text-slate-600 uppercase">CFO</span>
                                <span class="text-[9px] font-extrabold px-2 py-0.5 rounded bg-slate-100 text-slate-600 uppercase">COO</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer Landing -->
            <footer
                class="px-8 py-4 border-t border-slate-200 bg-white flex items-center justify-between text-xs text-slate-400 font-medium"
            >
                <span>© 2026 Aipreneur. Powered by SQL + Laravel.</span>
                <span>HQ Jakarta, Indonesia</span>
            </footer>
        </div>

        <!-- =========================================================================
             DETAIL OKR VIEW (Tampilan Rincian OKR)
             ========================================================================= -->
        <div v-else-if="viewMode === 'detail'">
            <!-- Top Bar Header Detail -->
            <header
                class="bg-white border-b border-slate-200 sticky top-0 z-10 px-8 py-4 flex flex-wrap items-center justify-between gap-4 shadow-xs"
            >
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                        OKR — {{ quarter.label }}
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-500 uppercase tracking-wider">
                            {{ range.start }} s/d {{ range.end }}
                        </span>
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Aipreneur</span>
                    <select
                        :value="quarter.key"
                        class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @change="gantiKuartal($event.target.value)"
                    >
                        <option v-for="o in quarterOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                    </select>
                    <button
                        v-if="canManage"
                        class="bg-blue-600 text-white rounded-lg px-3.5 py-1.5 text-xs font-bold hover:bg-blue-700 shadow-xs"
                        @click="bukaObjective()"
                    >
                        + Buat Objective
                    </button>
                </div>
            </header>

            <!-- Main Content Area -->
            <div class="p-8 max-w-7xl mx-auto space-y-8">
                <!-- OKR Dashboard Overview Summary Cards -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900 tracking-tight">
                                Ringkasan Performa OKR {{ quarter.label }}
                            </h2>
                            <p class="text-xs text-slate-500">Overview seluruh Objective & Key Result per kuartal secara sekilas.</p>
                        </div>
                    </div>

                    <!-- Stat Cards Grid (Matching Screenshot Layout) -->
                    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
                        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Progress Rata-rata</p>
                            <p class="text-2xl font-extrabold text-blue-600">
                                {{ ringkasan.progress === null || ringkasan.progress === undefined ? '—' : ringkasan.progress + '%' }}
                            </p>
                        </div>
                        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Target Omzet</p>
                            <p class="text-2xl font-extrabold text-emerald-600">
                                {{ ringkasan.omset_target > 0 ? rpShort(ringkasan.omset_target) : 'Rp 0' }}
                            </p>
                            <p class="text-[10px] text-slate-400 font-semibold truncate" :title="'Rp ' + nfFull.format(ringkasan.omset_target || 0)">
                                Rp {{ nfFull.format(ringkasan.omset_target || 0) }}
                            </p>
                        </div>
                        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Objective</p>
                            <p class="text-2xl font-extrabold text-slate-800">{{ ringkasan.objectives || objectives.length }}</p>
                        </div>
                        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Key Result</p>
                            <p class="text-2xl font-extrabold text-slate-800">
                                {{
                                    ringkasan.key_results ||
                                    objectives.reduce((acc, o) => acc + (o.key_results ? o.key_results.length : 0), 0)
                                }}
                            </p>
                        </div>
                        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">On Track / Tercapai</p>
                            <p class="text-2xl font-extrabold text-emerald-600">{{ ringkasan.tercapai || 0 }}</p>
                        </div>
                        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Off Track / Tertinggal</p>
                            <p class="text-2xl font-extrabold text-amber-600">{{ ringkasan.tertinggal || 0 }}</p>
                        </div>
                    </div>

                    <!-- Objective Quick Cards Dashboard Grid -->
                    <div v-if="objectives.length" class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                        <div
                            v-for="(o, idx) in objectives"
                            :key="'overview-' + o.id"
                            class="bg-white border border-slate-200/90 rounded-xl p-5 shadow-2xs space-y-3 hover:border-blue-300 transition-colors"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-red-600"
                                    >OBJECTIVE {{ idx + 1 }}</span
                                >
                                <span
                                    v-if="o.priority"
                                    class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 uppercase"
                                >
                                    {{ o.priority.name }}
                                </span>
                            </div>
                            <h3 class="text-xs font-extrabold text-slate-800 leading-snug line-clamp-2" :title="o.title">{{ o.title }}</h3>
                            <div class="space-y-1.5 pt-1">
                                <div class="flex items-center justify-between text-[11px] font-semibold text-slate-600">
                                    <span>Pencapaian:</span>
                                    <span class="text-blue-600 font-extrabold">{{
                                        o.progress === null || o.progress === undefined ? '0%' : o.progress + '%'
                                    }}</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div
                                        class="bg-blue-600 h-full rounded-full transition-all"
                                        :style="{ width: Math.min(100, Math.max(0, o.progress || 0)) + '%' }"
                                    ></div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-slate-500 border-t border-slate-100 pt-2">
                                <span
                                    >PIC: <strong class="text-slate-700">{{ o.omset_owner_name || '-' }}</strong></span
                                >
                                <span>{{ o.key_results ? o.key_results.length : 0 }} Key Result</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="!objectives.length"
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 shadow-xs"
                >
                    <div>
                        <p class="text-base font-bold text-slate-800">Belum ada Objective untuk {{ quarter.label }}</p>
                        <p class="mt-1 text-xs text-slate-500">Mulai dengan menambah Objective, lalu isi Key Result-nya.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="canManage"
                            class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg"
                            @click="bukaObjective()"
                        >
                            + Buat Objective
                        </button>
                        <button
                            v-if="canManage && bisaSalin"
                            class="px-4 py-2 text-xs font-bold text-slate-700 border border-slate-300 bg-white hover:bg-slate-50 rounded-lg"
                            @click="salinKuartalLalu"
                        >
                            Salin dari {{ kuartalLaluLabel }}
                        </button>
                    </div>
                </div>

                <!-- Objectives List (Design Matching Screenshot Exactly) -->
                <div
                    v-for="(o, oIdx) in objectives"
                    :key="o.id"
                    class="bg-white rounded-xl border border-slate-200/90 p-8 space-y-6 shadow-xs"
                >
                    <!-- Objective Header -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-extrabold uppercase tracking-wider text-red-600">OBJECTIVE {{ oIdx + 1 }}</span>
                                <span
                                    v-if="o.priority"
                                    class="text-[11px] font-bold px-2.5 py-0.5 rounded bg-blue-100 text-blue-700 uppercase"
                                >
                                    {{ o.priority.name }}
                                </span>
                                <span v-if="o.omset_owner_name" class="text-xs text-slate-500">
                                    Penanggung jawab: <strong class="text-slate-800">{{ o.omset_owner_name }}</strong>
                                </span>
                            </div>
                            <div v-if="canManage" class="flex items-center gap-1.5">
                                <button
                                    class="p-2 rounded-lg border border-slate-200 text-slate-400 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50 transition-colors"
                                    title="Edit Objective"
                                    @click="bukaObjective(o)"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z"
                                        />
                                    </svg>
                                </button>
                                <button
                                    class="p-2 rounded-lg border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 transition-colors"
                                    title="Hapus Objective"
                                    @click="hapusObjective(o)"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">{{ o.title }}</h2>

                        <p v-if="o.description" class="text-xs text-slate-600 leading-relaxed whitespace-pre-line max-w-5xl">
                            {{ o.description }}
                        </p>

                        <div v-if="canManage" class="pt-2">
                            <button
                                class="text-xs font-bold px-3.5 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow-xs"
                                @click="bukaKr(o)"
                            >
                                + Key Result
                            </button>
                        </div>
                    </div>

                    <!-- Key Results Container -->
                    <div class="space-y-8 pl-5 border-l-2 border-blue-600">
                        <div v-for="(kr, krIdx) in o.key_results" :key="kr.id" class="space-y-4">
                            <!-- Key Result Header -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-blue-600"
                                        >KEY RESULT {{ oIdx + 1 }}.{{ krIdx + 1 }}</span
                                    >
                                    <div v-if="canManage" class="flex items-center gap-1">
                                        <button
                                            v-if="kr.source === 'manual'"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                                            title="Update Realisasi"
                                            @click="bukaAktual(kr)"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                            title="Edit Key Result"
                                            @click="bukaKr(o, kr)"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Hapus Key Result"
                                            @click="hapusKr(kr)"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <h3 class="text-lg font-extrabold text-slate-900">{{ kr.title }}</h3>

                                <p v-if="kr.description" class="text-xs text-slate-500 leading-relaxed max-w-3xl">{{ kr.description }}</p>

                                <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-slate-600 font-medium">
                                    <span
                                        >Metrik:
                                        <strong class="text-slate-900">{{
                                            kr.unit === 'rupiah' ? 'Rp' : kr.unit === 'persen' ? 'Persentase' : 'Jumlah'
                                        }}</strong></span
                                    >
                                    <span
                                        >Target:
                                        <strong class="text-slate-900">{{
                                            kr.unit === 'rupiah' ? fmtFull(kr.target, 'rupiah') : kr.target
                                        }}</strong></span
                                    >
                                    <span v-if="kr.owner_name"
                                        >Penanggung jawab: <strong class="text-slate-900">{{ kr.owner_name }}</strong></span
                                    >
                                    <span>Tenggat: <strong class="text-slate-900">30 Sep 2026</strong></span>
                                </div>

                                <!-- Deskripsi + link ke board terkait -->
                                <div v-if="kr.kartu && kr.kartu.length" class="pt-2">
                                    <p v-if="kr.kartu[0]?.description" class="text-xs text-slate-500 leading-relaxed mb-2">
                                        {{ kr.kartu[0].description }}
                                    </p>
                                    <a
                                        :href="kr.source === 'kartu'
                                            ? `/pipelines/kanban?category=${kr.kartu[0]?.board || 'todo'}&card=${kr.kartu[0]?.id}`
                                            : `/pipelines?category=sales&card=${kr.kartu[0]?.id}`"
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 rounded-lg px-3 py-1.5 transition-colors"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                        {{ kr.source === 'kartu' ? 'Buka di Kanban →' : 'Buka di Sales →' }}
                                    </a>
                                </div>
                            </div>

                            <!-- Cards (Workstreams Grid - 2 columns per row like screenshot) -->
                            <div v-if="kr.kartu && kr.kartu.length" class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                <div
                                    v-for="card in kr.kartu"
                                    :key="card.id"
                                    class="bg-white border border-slate-200/90 rounded-xl p-5 space-y-3 shadow-2xs hover:border-blue-300 transition-colors"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-2.5">
                                            <span
                                                class="w-4 h-4 rounded-full border-2 border-slate-400 flex items-center justify-center flex-shrink-0"
                                            >
                                                <span v-if="card.selesai" class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                            </span>
                                            <h4 class="text-xs font-extrabold text-slate-800 leading-snug">{{ card.judul }}</h4>
                                        </div>
                                        <button
                                            v-if="canManage"
                                            class="text-[11px] font-semibold text-slate-400 hover:text-slate-600"
                                            @click="bukaKr(o, kr)"
                                        >
                                            Edit Tugas
                                        </button>
                                    </div>

                                    <p v-if="card.description" class="text-xs text-slate-600 leading-relaxed pl-6">
                                        {{ card.description }}
                                    </p>

                                    <div
                                        class="flex flex-wrap items-center justify-between text-xs text-slate-500 pt-2 pl-6 border-t border-slate-100 gap-2 font-medium"
                                    >
                                        <span v-if="card.pic"
                                            >PIC: <strong class="text-slate-800">{{ card.pic }}</strong></span
                                        >
                                        <span v-if="card.deadline"
                                            >Tenggat: <strong class="text-slate-800">{{ card.deadline }}</strong></span
                                        >
                                    </div>

                                    <div class="pl-6 text-xs text-blue-600 font-semibold">
                                        <a
                                            :href="`/kanban?category=${card.board || 'todo'}&card=${card.id}`"
                                            class="hover:underline flex items-center gap-1"
                                        >
                                            Kanban: Todolist Tim &gt; {{ card.pic || 'Tim' }}
                                            <svg class="w-3 h-3 text-blue-500 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                                />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Objective -->
        <ModalWrap v-if="objModal" @close="objModal = null">
            <h3 class="text-lg font-bold text-slate-800">
                {{ objModal === 'baru' ? 'Objective baru' : 'Ubah Objective' }} — {{ quarter.label }}
            </h3>
            <form class="mt-4 space-y-4" @submit.prevent="simpanObjective">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Judul Objective</label>
                    <input
                        v-model="objForm.title"
                        type="text"
                        placeholder="Meningkatkan Omzet E-Commerce"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p v-if="objForm.errors.title" class="text-xs text-red-600 mt-1">{{ objForm.errors.title }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Deskripsi / Alasan Dipilih</label>
                    <textarea
                        v-model="objForm.description"
                        rows="4"
                        placeholder="Fokus pada peningkatan omzet e-commerce dan pengembangan jaringan..."
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    ></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tag Role (misal: COO AI, CFO AI)</label>
                        <input
                            v-model="objForm.priority_name"
                            type="text"
                            placeholder="COO AI"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Penanggung Jawab (PIC)</label>
                        <select
                            v-model="objForm.omset_owner_id"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">— pilih PIC —</option>
                            <option v-for="person in staff" :key="person.id" :value="person.id">
                                {{ person.name }} ({{ person.role }})
                            </option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Target Omzet Kuartal (Rp)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-400">Rp</span>
                        <input
                            v-model="objForm.omset_target"
                            type="number"
                            min="0"
                            step="1000"
                            placeholder="500000000"
                            class="w-full border border-slate-200 rounded-lg pl-10 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700"
                        @click="objModal = null"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="objForm.processing"
                        class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>

        <!-- Modal Key Result — Desain Baru -->
        <ModalWrap v-if="krModal" width="max-w-lg" @close="krModal = null">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 flex-shrink-0 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                        />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 leading-tight">
                        {{ krModal.mode === 'baru' ? 'Key Result Baru' : 'Edit Key Result' }}
                    </h3>
                    <p class="text-xs text-slate-400">Objective: {{ krModal.objective.title }}</p>
                </div>
            </div>

            <form class="space-y-5" @submit.prevent="simpanKr">
                <!-- Judul -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Judul Key Result</label>
                    <input v-model="krForm.title" type="text" placeholder="Contoh: Selesaikan 10 kartu produksi konten" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" />
                    <p v-if="krForm.errors.title" class="text-xs text-red-500 mt-1">{{ krForm.errors.title }}</p>
                </div>

                <!-- Deskripsi -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Deskripsi (opsional)</label>
                    <textarea v-model="krForm.description" rows="2" placeholder="Jelaskan konteks atau tujuan Key Result ini…" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-y"></textarea>
                </div>

                <!-- Board Kanban -->
                <div class="bg-emerald-50/60 border border-emerald-200 rounded-xl p-4 space-y-3">
                    <p class="text-[11px] font-semibold text-emerald-800">Realisasi = jumlah kartu Kanban yang selesai di board yang dipilih.</p>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Board Kanban</label>
                        <select v-model="krForm.board_key" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">— pilih board —</option>
                            <option v-for="b in kanbanBoards" :key="b.key" :value="b.key">{{ b.name }}</option>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Target akan diambil dari KPI Board yang bersangkutan.</p>
                        <p v-if="krForm.errors.board_key" class="text-xs text-red-500 mt-1">{{ krForm.errors.board_key }}</p>
                    </div>
                </div>

                <!-- Prioritas + PIC -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Prioritas</label>
                        <div class="flex gap-2">
                            <button
                                v-for="p in priorities"
                                :key="p.name"
                                type="button"
                                :class="[
                                    'flex-1 rounded-lg border px-3 py-2 text-xs font-bold transition-all',
                                    krForm.priority_name === p.name
                                        ? 'border-transparent text-white shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300',
                                ]"
                                :style="krForm.priority_name === p.name ? { backgroundColor: p.color } : {}"
                                @click="krForm.priority_name = krForm.priority_name === p.name ? '' : p.name"
                            >
                                {{ p.name }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Penanggung Jawab</label>
                        <select
                            v-model="krForm.assigned_to"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">— otomatis / belum ditentukan —</option>
                            <option v-for="person in staff" :key="person.id" :value="person.id">
                                {{ person.name }} ({{ person.role }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Deadline -->
                <div v-if="krForm.source !== 'auto'">
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tenggat (opsional)</label>
                    <input
                        v-model="krForm.deadline"
                        type="date"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>

                <!-- Workstream / Card Eksekusi (hanya saat baru) -->
                <div v-if="krModal.mode === 'baru'">
                    <button
                        type="button"
                        class="flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-700 transition-colors"
                        @click="showWorkstream = !showWorkstream"
                    >
                        <svg
                            class="w-4 h-4 transition-transform"
                            :class="showWorkstream ? 'rotate-90' : ''"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                        {{ showWorkstream ? 'Sembunyikan' : 'Buat Kartu Workstream / Task Utama (Opsional)' }}
                    </button>

                    <div v-if="showWorkstream" class="mt-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Board Kanban Tujuan</label>
                            <select
                                v-model="krForm.kanban_board_key"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="b in kanbanBoards" :key="b.key" :value="b.key">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Kolom Awal</label>
                            <select
                                v-model="krForm.kanban_column_key"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="c in executionColumns" :key="c.key" :value="c.key">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Label Kategori</label>
                            <select
                                v-model="krForm.card_category"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">— tanpa label —</option>
                                <option v-for="c in cardCategories" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Deskripsi Workstream</label>
                            <textarea
                                v-model="krForm.card_description"
                                rows="3"
                                placeholder="Jalankan workstream ini berdasarkan diagnosis panel..."
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Pesan error sumber -->
                <p v-if="krForm.errors.source" class="text-xs text-red-500">{{ krForm.errors.source }}</p>
                <p v-if="krForm.errors.metric" class="text-xs text-red-500">{{ krForm.errors.metric }}</p>
                <p v-if="krForm.errors.kanban_column_key" class="text-xs text-red-500">{{ krForm.errors.kanban_column_key }}</p>
                <p v-if="krForm.errors.card_category" class="text-xs text-red-500">{{ krForm.errors.card_category }}</p>

                <!-- Tombol aksi -->
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        class="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl transition-colors"
                        @click="krModal = null"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="krForm.processing"
                        class="px-5 py-2.5 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-xl shadow-sm transition-all disabled:opacity-60"
                    >
                        {{ krForm.processing ? 'Menyimpan…' : krModal.mode === 'baru' ? 'Simpan Key Result' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </form>
        </ModalWrap>

        <!-- Modal Realisasi -->
        <ModalWrap v-if="aktualModal" width="max-w-sm" @close="aktualModal = null">
            <h3 class="text-lg font-bold text-slate-800">Perbarui Realisasi / Baseline</h3>
            <p class="text-xs text-slate-500 mt-1 mb-3">{{ aktualModal.title }}</p>
            <form class="space-y-3" @submit.prevent="simpanAktual">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nilai Realisasi Aktual Saat Ini</label>
                    <input
                        v-model="aktualForm.actual_manual"
                        type="number"
                        step="any"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-xs font-semibold text-slate-500" @click="aktualModal = null">Batal</button>
                    <button
                        type="submit"
                        :disabled="aktualForm.processing"
                        class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

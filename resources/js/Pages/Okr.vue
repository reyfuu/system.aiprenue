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

const periodOmsetModal = ref(false);
const periodOmsetForm = useForm({
    q: '',
    omset_target: '',
});

const editOmzetPerusahaan = () => {
    periodOmsetForm.q = props.quarter.key;
    periodOmsetForm.omset_target = props.ringkasan.omset_target || '';
    periodOmsetForm.clearErrors();
    periodOmsetModal.value = true;
};

const simpanPeriodOmset = () => {
    periodOmsetForm.transform((data) => ({
        ...data,
        omset_target: data.omset_target ? String(data.omset_target).replace(/[^0-9]/g, '') : null,
    })).put('/okr/omset-target', {
        preserveScroll: true,
        onSuccess: () => {
            periodOmsetModal.value = false;
        },
    });
};

const editOmzetBrand = (brandName) => {
    const o = props.objectives.find((obj) => obj.priority?.name === brandName);
    if (o) {
        bukaObjective(o);
    } else {
        bukaObjective(null);
        objForm.title = `Objective Omzet Penjualan ${brandName}`;
        objForm.priority_name = brandName;
    }
};

const simpanObjective = () => {
    const tutup = {
        preserveScroll: true,
        onSuccess: () => {
            objModal.value = null;
        },
    };
    objForm.transform((data) => ({
        ...data,
        omset_owner_id: data.omset_owner_id || null,
        omset_target: data.omset_target ? String(data.omset_target).replace(/[^0-9]/g, '') : null,
    }));
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
        label: 'KPI Board / Kartu',
        desc: 'Realisasi = kartu Kanban yang selesai',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    },
    {
        value: 'auto',
        label: 'Metrik Otomatis (Omzet / View)',
        desc: 'Realisasi dari Pembukuan / Insight',
        icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
    },
    {
        value: 'manual',
        label: 'Manual / Checklist Target',
        desc: 'Realisasi diisi manual / dicentang per progres',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    },
];

const unitOptions = [
    { value: 'angka', label: 'Angka / Jumlah' },
    { value: 'rupiah', label: 'Rupiah (Rp)' },
    { value: 'persen', label: 'Persentase (%)' },
];

const isKrComplete = (kr) => {
    if (kr.percent !== null && kr.percent !== undefined) {
        return kr.percent >= 100;
    }
    return kr.target > 0 && kr.actual >= kr.target;
};

const isObjectiveComplete = (o) => {
    if (!o.key_results || o.key_results.length === 0) {
        return o.progress !== null && o.progress >= 100;
    }
    return o.key_results.every((kr) => isKrComplete(kr));
};

const toggleKr = (kr) => {
    if (kr.source === 'manual') {
        const targetVal = isKrComplete(kr) ? 0 : (kr.target || 1);
        router.patch(`/okr/key-results/${kr.id}/actual`, {
            actual_manual: targetVal,
        }, { preserveScroll: true });
    }
};

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
    krForm.source = kr?.source ?? 'kartu';
    krForm.board_key = kr?.board_key ?? '';
    krForm.metric = kr?.metric ?? (Object.keys(props.metrics)[0] || 'omset');
    krForm.target = kr?.target ?? 0;
    krForm.unit = kr?.unit ?? (krForm.metric === 'omset' ? 'rupiah' : 'angka');
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
        <!-- DETAIL OKR VIEW (Tampilan Rincian OKR Langsung) -->
        <div class="min-h-screen bg-slate-50">
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
                        class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-4 py-2 text-sm font-semibold transition inline-flex items-center gap-1.5 shadow-sm"
                        @click="bukaObjective()"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Buat Objective
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
                        <div class="bg-white border border-emerald-200 bg-emerald-50/20 rounded-xl p-4 shadow-2xs space-y-1 relative group">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold text-emerald-800 uppercase tracking-wider">Target Omzet Kuartal</p>
                                <button
                                    v-if="canManage"
                                    class="text-[10px] font-bold text-blue-600 hover:underline"
                                    title="Edit Omzet Total"
                                    @click="editOmzetPerusahaan()"
                                >
                                    ✏️ Edit
                                </button>
                            </div>
                            <p class="text-2xl font-extrabold text-emerald-600">
                                {{ ringkasan.omset_target > 0 ? rpShort(ringkasan.omset_target) : 'Rp 0' }}
                            </p>
                            <p class="text-[10px] text-emerald-700 font-semibold truncate">
                                Per Bulan: {{ ringkasan.omset_target > 0 ? rpShort(ringkasan.omset_target / 3) : 'Rp 0' }}
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

                    <!-- Omzet Breakdown per Bisnis (FK & Aipreneur) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                        <div class="bg-white border border-emerald-200/80 rounded-xl p-4 shadow-2xs flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 uppercase">OMZET FK</span>
                                <h4 class="text-xs font-bold text-slate-800 mt-1">Lini Produk Fashion &amp; Konsumen</h4>
                                <p class="text-[10px] text-slate-500 font-semibold mt-0.5">
                                    Realisasi dari Pembukuan
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-extrabold text-emerald-600">
                                    Rp {{ nfFull.format(ringkasan.omset_fk_actual || 0) }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-white border border-indigo-200/80 rounded-xl p-4 shadow-2xs flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 uppercase">OMZET AIPRENEUR</span>
                                <h4 class="text-xs font-bold text-slate-800 mt-1">Ekosistem Portal &amp; Subskripsi</h4>
                                <p class="text-[10px] text-slate-500 font-semibold mt-0.5">
                                    Realisasi dari Pembukuan
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-extrabold text-indigo-600">
                                    Rp {{ nfFull.format(ringkasan.omset_aipreneur_actual || 0) }}
                                </p>
                            </div>
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
                                <span>PIC: <strong class="text-slate-700">{{ o.omset_owner_name || '-' }}</strong></span>
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
                                <span
                                    v-if="isObjectiveComplete(o)"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300"
                                    title="Semua Key Result selesai! Objective tercentang otomatis."
                                >
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Objective Selesai (Otomatis Tercentang)
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

                        <div class="flex items-center gap-3">
                            <span
                                v-if="isObjectiveComplete(o)"
                                class="w-6 h-6 rounded-md bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 font-extrabold text-xs"
                            >
                                ✓
                            </span>
                            <h2 :class="['text-xl font-extrabold tracking-tight', isObjectiveComplete(o) ? 'text-emerald-900 line-through' : 'text-slate-900']">
                                {{ o.title }}
                            </h2>
                        </div>

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
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="flex items-center justify-center w-5 h-5 rounded border-2 transition-all flex-shrink-0"
                                            :class="isKrComplete(kr)
                                                ? 'bg-emerald-500 border-emerald-500 text-white shadow-xs'
                                                : kr.source === 'manual' ? 'border-slate-300 bg-white hover:border-blue-500 text-transparent cursor-pointer' : 'border-slate-200 bg-slate-100 text-transparent cursor-default'"
                                            :title="isKrComplete(kr) ? 'Key Result Selesai (Tercentang)' : (kr.source === 'manual' ? 'Klik untuk centang selesai' : 'Realisasi otomatis/kartu')"
                                            @click="toggleKr(kr)"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <span class="text-xs font-bold uppercase tracking-wider text-blue-600"
                                            >KEY RESULT {{ oIdx + 1 }}.{{ krIdx + 1 }}</span
                                        >
                                    </div>
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

                                <h3 :class="['text-lg font-extrabold transition-colors', isKrComplete(kr) ? 'text-slate-500 line-through' : 'text-slate-900']">
                                    {{ kr.title }}
                                </h3>

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
                                            ? `/pipelines/kanban?category=${kr.kartu[0]?.board || 'todolist'}&card=${kr.kartu[0]?.id}`
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
                                            Edit
                                        </button>
                                    </div>

                                    <p v-if="card.description" class="text-xs text-slate-600 leading-relaxed pl-6 whitespace-pre-line">
                                        {{ card.description }}
                                    </p>
                                    <p v-else-if="card.is_master && kr.description" class="text-xs text-slate-500 leading-relaxed pl-6 italic">
                                        {{ kr.description }}
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
                                            :href="`/pipelines/kanban?category=${card.board || 'todo'}&card=${card.id}`"
                                            class="hover:underline flex items-center gap-1"
                                        >
                                            Kanban: {{ card.board_name || 'Board' }} &gt; {{ card.pic || 'Tim' }}
                                            <svg class="w-3.5 h-3.5 text-blue-500 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <p v-if="objForm.errors.priority_name" class="text-xs text-red-600 mt-1">{{ objForm.errors.priority_name }}</p>
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
                        <p v-if="objForm.errors.omset_owner_id" class="text-xs text-red-600 mt-1">{{ objForm.errors.omset_owner_id }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Target Omzet Kuartal (Rp)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-400">Rp</span>
                        <input
                            v-model="objForm.omset_target"
                            type="text"
                            inputmode="numeric"
                            placeholder="500.000.000"
                            class="w-full border border-slate-200 rounded-lg pl-10 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <p v-if="objForm.errors.omset_target" class="text-xs text-red-600 mt-1">{{ objForm.errors.omset_target }}</p>
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

        <!-- Modal Target Omzet Kuartal Perusahaan -->
        <ModalWrap v-if="periodOmsetModal" @close="periodOmsetModal = false">
            <h3 class="text-lg font-bold text-slate-800">
                Target Omzet Total Perusahaan — {{ quarter.label }}
            </h3>
            <p class="text-xs text-slate-500 mt-1">
                Target omzet kuartal ini terpisah secara mandiri dan tidak dipaksa mengambil gabungan omzet brand.
            </p>
            <form class="mt-4 space-y-4" @submit.prevent="simpanPeriodOmset">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Target Omzet Kuartal (Rp)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-400">Rp</span>
                        <input
                            v-model="periodOmsetForm.omset_target"
                            type="text"
                            inputmode="numeric"
                            placeholder="1.000.000.000"
                            class="w-full border border-slate-200 rounded-lg pl-10 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <p v-if="periodOmsetForm.errors.omset_target" class="text-xs text-red-600 mt-1">
                        {{ periodOmsetForm.errors.omset_target }}
                    </p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700"
                        @click="periodOmsetModal = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="periodOmsetForm.processing"
                        class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg"
                    >
                        Simpan Target Omzet
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

                <!-- Sumber Realisasi (Jenis KR) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Sumber Realisasi / Jenis Key Result</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="s in sourceOptions"
                            :key="s.value"
                            type="button"
                            :class="[
                                'p-3 rounded-xl border text-left transition-all',
                                krForm.source === s.value
                                    ? 'border-blue-500 bg-blue-50/60 ring-2 ring-blue-500/20'
                                    : 'border-slate-200 bg-white hover:border-slate-300',
                            ]"
                            @click="krForm.source = s.value"
                        >
                            <p class="text-xs font-bold text-slate-800">{{ s.label }}</p>
                            <p class="text-[10px] text-slate-500 mt-0.5 leading-tight">{{ s.desc }}</p>
                        </button>
                    </div>
                </div>

                <!-- Opsi Board Kanban -->
                <div v-if="krForm.source === 'kartu'" class="bg-emerald-50/60 border border-emerald-200 rounded-xl p-4 space-y-3">
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

                <!-- Opsi Metrik Otomatis (Omzet / View / Subscriber) -->
                <div v-if="krForm.source === 'auto'" class="bg-blue-50/60 border border-blue-200 rounded-xl p-4 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Metrik Otomatis</label>
                        <select v-model="krForm.metric" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                            <option v-for="(mLabel, mKey) in metrics" :key="mKey" :value="mKey">{{ mLabel }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Target Key Result</label>
                            <input v-model="krForm.target" type="number" step="any" placeholder="1000000" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Satuan</label>
                            <select v-model="krForm.unit" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option v-for="u in unitOptions" :key="u.value" :value="u.value">{{ u.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Opsi Manual / Target Centang -->
                <div v-if="krForm.source === 'manual'" class="bg-amber-50/60 border border-amber-200 rounded-xl p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-amber-900 mb-1">Target Key Result</label>
                            <input v-model="krForm.target" type="number" step="any" placeholder="10" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-amber-900 mb-1">Satuan</label>
                            <select v-model="krForm.unit" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                                <option v-for="u in unitOptions" :key="u.value" :value="u.value">{{ u.label }}</option>
                            </select>
                        </div>
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

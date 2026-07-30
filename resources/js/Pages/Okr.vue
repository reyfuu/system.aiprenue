<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import ModalWrap from '../ModalWrap.vue';

const props = defineProps({
    quarter: Object,          // { year, quarter, key, label }
    quarterOptions: { type: Array, default: () => [] },
    range: Object,            // { start, end } — rentang tanggal kuartal
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
});

const nfFull = new Intl.NumberFormat('id-ID');
const fmtFull = (n, unit) => (unit === 'rupiah' ? 'Rp' : '') + nfFull.format(Number(n || 0)) + (unit === 'persen' ? '%' : '');

const gantiKuartal = (key) => router.get('/okr', { q: key }, { preserveScroll: true });

const salinKuartalLalu = () => {
    if (!confirm(`Salin semua Objective & target dari ${props.kuartalLaluLabel} ke ${props.quarter.label}? Realisasinya tidak ikut disalin.`)) return;
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
const krModal = ref(null);
const krForm = useForm({ objective_id: null, title: '', source: 'manual', board_key: '', metric: '', target: 0, unit: 'angka', priority_name: '', kanban_board_key: '', kanban_column_key: '', card_category: '', card_description: '', assigned_to: '', deadline: '' });
const executionColumns = computed(() => props.kanbanColumns[krForm.kanban_board_key] ?? []);
const masterCard = computed(() => krModal.value?.kr?.kartu?.find((k) => k.is_master) ?? null);

watch(() => krForm.kanban_board_key, () => {
    if (!executionColumns.value.some((column) => column.key === krForm.kanban_column_key)) {
        krForm.kanban_column_key = executionColumns.value[0]?.key ?? '';
    }
});

const bukaKr = (objective, kr = null) => {
    krModal.value = { mode: kr ? 'edit' : 'baru', objective, kr };
    krForm.objective_id = objective.id;
    krForm.title = kr?.title ?? '';
    krForm.source = kr?.source ?? 'manual';
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
        <!-- Top Bar Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-10 px-8 py-4 flex flex-wrap items-center justify-between gap-4 shadow-xs">
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                    OKR — {{ quarter.label }}
                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-500 uppercase tracking-wider">
                        {{ range.start }} s/d {{ range.end }}
                    </span>
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">SKINKU B2B Distributor Portal</span>
                <select
                    :value="quarter.key"
                    class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @change="gantiKuartal($event.target.value)"
                >
                    <option v-for="o in quarterOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                </select>
                <button v-if="canManage" class="bg-blue-600 text-white rounded-lg px-3.5 py-1.5 text-xs font-bold hover:bg-blue-700 shadow-xs" @click="bukaObjective()">
                    + Edit Objective
                </button>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="p-8 max-w-7xl mx-auto space-y-8">
            <!-- OKR Dashboard Overview Summary Cards -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Ringkasan Performa OKR {{ quarter.label }}</h2>
                        <p class="text-xs text-slate-500">Overview seluruh Objective & Key Result per kuartal secara sekilas.</p>
                    </div>
                </div>

                <!-- Stat Cards Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Progress Rata-rata</p>
                        <p class="text-2xl font-extrabold text-blue-600">
                            {{ ringkasan.progress === null || ringkasan.progress === undefined ? '—' : ringkasan.progress + '%' }}
                        </p>
                    </div>
                    <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Objective</p>
                        <p class="text-2xl font-extrabold text-slate-800">{{ ringkasan.objectives || objectives.length }}</p>
                    </div>
                    <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs space-y-1">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Key Result</p>
                        <p class="text-2xl font-extrabold text-slate-800">{{ ringkasan.key_results || objectives.reduce((acc, o) => acc + (o.key_results ? o.key_results.length : 0), 0) }}</p>
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
                    <div v-for="(o, idx) in objectives" :key="'overview-' + o.id" class="bg-white border border-slate-200/90 rounded-xl p-5 shadow-2xs space-y-3 hover:border-blue-300 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-red-600">OBJECTIVE {{ idx + 1 }}</span>
                            <span v-if="o.priority" class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 uppercase">
                                {{ o.priority.name }}
                            </span>
                        </div>
                        <h3 class="text-xs font-extrabold text-slate-800 leading-snug line-clamp-2" :title="o.title">{{ o.title }}</h3>
                        <div class="space-y-1.5 pt-1">
                            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-600">
                                <span>Pencapaian:</span>
                                <span class="text-blue-600 font-extrabold">{{ o.progress === null || o.progress === undefined ? '0%' : o.progress + '%' }}</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div class="bg-blue-600 h-full rounded-full transition-all" :style="{ width: Math.min(100, Math.max(0, o.progress || 0)) + '%' }"></div>
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
            <div v-if="!objectives.length" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 shadow-xs">
                <div>
                    <p class="text-base font-bold text-slate-800">Belum ada Objective untuk {{ quarter.label }}</p>
                    <p class="mt-1 text-xs text-slate-500">Mulai dengan menambah Objective, lalu isi Key Result-nya.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button v-if="canManage" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg" @click="bukaObjective()">
                        + Buat Objective
                    </button>
                    <button v-if="canManage && bisaSalin" class="px-4 py-2 text-xs font-bold text-slate-700 border border-slate-300 bg-white hover:bg-slate-50 rounded-lg" @click="salinKuartalLalu">
                        Salin dari {{ kuartalLaluLabel }}
                    </button>
                </div>
            </div>

            <!-- Objectives List (Design Matching Screenshot Exactly) -->
            <div v-for="(o, oIdx) in objectives" :key="o.id" class="bg-white rounded-xl border border-slate-200/90 p-8 space-y-6 shadow-xs">
                <!-- Objective Header -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-extrabold uppercase tracking-wider text-red-600">OBJECTIVE {{ oIdx + 1 }}</span>
                            <span v-if="o.priority" class="text-[11px] font-bold px-2.5 py-0.5 rounded bg-blue-100 text-blue-700 uppercase">
                                {{ o.priority.name }}
                            </span>
                            <span v-if="o.omset_owner_name" class="text-xs text-slate-500">
                                Penanggung jawab: <strong class="text-slate-800">{{ o.omset_owner_name }}</strong>
                            </span>
                        </div>
                        <div v-if="canManage" class="flex items-center gap-2">
                            <button class="text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" @click="bukaObjective(o)">
                                Edit Objective
                            </button>
                            <button class="text-xs font-bold px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50" @click="hapusObjective(o)">
                                Hapus
                            </button>
                        </div>
                    </div>

                    <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">{{ o.title }}</h2>

                    <p v-if="o.description" class="text-xs text-slate-600 leading-relaxed whitespace-pre-line max-w-5xl">
                        {{ o.description }}
                    </p>

                    <div v-if="canManage" class="pt-2">
                        <button class="text-xs font-bold px-3.5 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 shadow-xs" @click="bukaKr(o)">
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
                                <span class="text-xs font-bold uppercase tracking-wider text-blue-600">KEY RESULT {{ oIdx + 1 }}.{{ krIdx + 1 }}</span>
                                <div v-if="canManage" class="flex items-center gap-2">
                                    <button v-if="kr.source === 'manual'" class="text-[11px] font-bold text-blue-600 hover:underline" @click="bukaAktual(kr)">Edit KR</button>
                                    <button class="text-[11px] font-bold text-slate-500 hover:text-slate-700" @click="bukaKr(o, kr)">Edit</button>
                                    <button class="text-[11px] font-bold text-red-500 hover:text-red-700" @click="hapusKr(kr)">Hapus</button>
                                </div>
                            </div>

                            <h3 class="text-lg font-extrabold text-slate-900">{{ kr.title }}</h3>

                            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-slate-600 font-medium">
                                <span>Metrik: <strong class="text-slate-900">{{ kr.unit === 'rupiah' ? 'Rp' : kr.unit === 'persen' ? 'Persentase' : 'Jumlah' }}</strong></span>
                                <span>Target: <strong class="text-slate-900">{{ kr.unit === 'rupiah' ? fmtFull(kr.target, 'rupiah') : kr.target }}</strong></span>
                                <span v-if="kr.owner_name">Penanggung jawab: <strong class="text-slate-900">{{ kr.owner_name }}</strong></span>
                                <span>Tenggat: <strong class="text-slate-900">30 Sep 2026</strong></span>
                            </div>

                            <!-- Alert Messages (Yellow / Green boxes matching screenshot) -->
                            <div class="pt-2 space-y-2 text-xs">
                                <p v-if="kr.actual > 0" class="text-amber-800 bg-amber-50/90 px-3.5 py-2 rounded-lg border border-amber-200/70 font-semibold">
                                    <strong>Baseline aktual: {{ kr.actual_manual ? fmtFull(kr.actual_manual, kr.unit) : kr.actual }} — Penjualan sebesar {{ fmtFull(kr.actual, kr.unit) }} dengan gap {{ fmtFull(kr.target - kr.actual, kr.unit) }} ke target.</strong>
                                </p>
                                <p v-else class="text-amber-800 bg-amber-50/90 px-3.5 py-2 rounded-lg border border-amber-200/70 font-semibold">
                                    <strong>Perlu validasi:</strong> Baseline spesifik Key Result ini belum dipilih Orchestrator dan harus divalidasi terhadap bukti panel sebelum eksekusi.
                                </p>
                                <p class="text-amber-800 bg-amber-50/90 px-3.5 py-2 rounded-lg border border-amber-200/70 leading-relaxed">
                                    <strong>Gap ke target:</strong> Gap target e-commerce: Target omzet Rp500.000.000 per bulan vs. basis pencapaian bulan lalu masih tidak tersedia (asumsi diperlukan validasi). Untuk distributor mencapai Rp9.000.000.000 per kuartal juga memerlukan strategi validasi fundamen berdasarkan hubungan dan omzet masing-masing distributor aktif.
                                </p>
                            </div>
                        </div>

                        <!-- Cards (Workstreams Grid - 2 columns per row like screenshot) -->
                        <div v-if="kr.kartu && kr.kartu.length" class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                            <div v-for="card in kr.kartu" :key="card.id" class="bg-white border border-slate-200/90 rounded-xl p-5 space-y-3 shadow-2xs hover:border-blue-300 transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-4 h-4 rounded-full border-2 border-slate-400 flex items-center justify-center flex-shrink-0">
                                            <span v-if="card.selesai" class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                        </span>
                                        <h4 class="text-xs font-extrabold text-slate-800 leading-snug">{{ card.judul }}</h4>
                                    </div>
                                    <button v-if="canManage" class="text-[11px] font-semibold text-slate-400 hover:text-slate-600" @click="bukaKr(o, kr)">Edit</button>
                                </div>

                                <p v-if="card.description" class="text-xs text-slate-600 leading-relaxed pl-6">
                                    {{ card.description }}
                                </p>

                                <div class="flex flex-wrap items-center justify-between text-xs text-slate-500 pt-2 pl-6 border-t border-slate-100 gap-2 font-medium">
                                    <span v-if="card.pic">PIC: <strong class="text-slate-800">{{ card.pic }}</strong></span>
                                    <span v-if="card.deadline">Tenggat: <strong class="text-slate-800">{{ card.deadline }}</strong></span>
                                </div>

                                <div class="pl-6 text-xs text-blue-600 font-semibold">
                                    <a :href="`/kanban?category=${card.board || 'skinku_management'}&card=${card.id}`" class="hover:underline flex items-center gap-1">
                                        Kanban: Task SKINKU Management &gt; To Do List {{ card.pic || 'Devrina' }}
                                        <svg class="w-3 h-3 text-blue-500 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Objective -->
        <ModalWrap v-if="objModal" @close="objModal = null">
            <h3 class="text-lg font-bold text-slate-800">{{ objModal === 'baru' ? 'Objective baru' : 'Ubah Objective' }} — {{ quarter.label }}</h3>
            <form class="mt-4 space-y-4" @submit.prevent="simpanObjective">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Judul Objective</label>
                    <input v-model="objForm.title" type="text" placeholder="Meningkatkan Omzet E-Commerce SKINKU" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    <p v-if="objForm.errors.title" class="text-xs text-red-600 mt-1">{{ objForm.errors.title }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Deskripsi / Alasan Dipilih</label>
                    <textarea v-model="objForm.description" rows="4" placeholder="SKINKU berfokus pada peningkatan omzet e-commerce dan distributor..." class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tag Role (misal: COO AI, CFO AI)</label>
                        <input v-model="objForm.priority_name" type="text" placeholder="COO AI" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Penanggung Jawab (PIC)</label>
                        <select v-model="objForm.omset_owner_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— pilih PIC —</option>
                            <option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }} ({{ person.role }})</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Target Omzet Kuartal (Rp)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-400">Rp</span>
                        <input v-model="objForm.omset_target" type="number" min="0" step="1000" placeholder="500000000" class="w-full border border-slate-200 rounded-lg pl-10 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700" @click="objModal = null">Batal</button>
                    <button type="submit" :disabled="objForm.processing" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- Modal Key Result -->
        <ModalWrap v-if="krModal" @close="krModal = null">
            <h3 class="text-lg font-bold text-slate-800">{{ krModal.mode === 'baru' ? 'Key Result baru' : 'Ubah Key Result' }}</h3>
            <form class="mt-4 space-y-4" @submit.prevent="simpanKr">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Judul Key Result</label>
                    <input v-model="krForm.title" type="text" placeholder="Mencapai omzet e-commerce Rp500.000.000/bulan" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Target</label>
                        <input v-model="krForm.target" type="number" step="any" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Satuan Metrik</label>
                        <select v-model="krForm.unit" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="angka">Angka / Jumlah</option>
                            <option value="rupiah">Rupiah (Rp)</option>
                            <option value="persen">Persentase (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Penanggung Jawab (PIC)</label>
                        <select v-model="krForm.assigned_to" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="">— pilih PIC —</option>
                            <option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Tambahkan Workstream Task saat membuat KR baru -->
                <div v-if="krModal.mode === 'baru'" class="rounded-lg border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Buat Kartu Workstream / Task Utama (Opsional)</h4>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Board Kanban Tujuan</label>
                        <select v-model="krForm.kanban_board_key" class="w-full border border-slate-200 rounded-lg px-3 py-1.5 text-xs bg-white">
                            <option v-for="b in kanbanBoards" :key="b.key" :value="b.key">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Deskripsi Workstream</label>
                        <textarea v-model="krForm.card_description" rows="2" placeholder="Jalankan workstream ini berdasarkan diagnosis panel..." class="w-full border border-slate-200 rounded-lg px-3 py-1.5 text-xs bg-white"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-xs font-semibold text-slate-500" @click="krModal = null">Batal</button>
                    <button type="submit" :disabled="krForm.processing" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg">Simpan</button>
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
                    <input v-model="aktualForm.actual_manual" type="number" step="any" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-xs font-semibold text-slate-500" @click="aktualModal = null">Batal</button>
                    <button type="submit" :disabled="aktualForm.processing" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg">Simpan</button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

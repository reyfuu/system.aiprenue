<script setup>
// Halaman CRM ringkas: ringkasan deal, filter nama/jenis, dan daftar lead/pipeline.
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Layout from '../Layout.vue';

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    cards: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ jenis: '', search: '', assignee: '', payment: '', status: '', sort_by: 'created_at', sort_dir: 'desc' }),
    },
    jenisList: { type: Object, default: () => ({}) },
    assigneeList: { type: Array, default: () => [] },
    paymentList: { type: Object, default: () => ({}) },
    assigneeSummary: { type: Array, default: () => [] },
    rate: { type: Number, default: 0 },
});

const filterJenis = ref(props.filters.jenis || '');
const keyword = ref(props.filters.search || '');
const filterAssignee = ref(props.filters.assignee || '');
const filterPayment = ref(props.filters.payment || '');
const filterStatus = ref(props.filters.status || '');
const sortBy = ref(props.filters.sort_by || 'created_at');
const sortDir = ref(props.filters.sort_dir || 'desc');
let filterDebounce = null;

const updateFilters = () => {
    const payload = {};
    if (filterJenis.value) {
        payload.jenis = filterJenis.value;
    }
    if (keyword.value) {
        payload.search = keyword.value;
    }
    if (filterAssignee.value) {
        payload.assignee = filterAssignee.value;
    }
    if (filterPayment.value) {
        payload.payment = filterPayment.value;
    }
    if (filterStatus.value) {
        payload.status = filterStatus.value;
    }
    if (sortBy.value) {
        payload.sort_by = sortBy.value;
    }
    if (sortDir.value) {
        payload.sort_dir = sortDir.value;
    }
    router.get('/crm', payload, { preserveState: false, preserveScroll: true });
};

const clearFilters = () => {
    filterJenis.value = '';
    keyword.value = '';
    filterAssignee.value = '';
    filterPayment.value = '';
    filterStatus.value = '';
    updateFilters();
};

const scheduleUpdateFilters = (delay = 300) => {
    if (filterDebounce) {
        clearTimeout(filterDebounce);
    }
    filterDebounce = setTimeout(() => {
        updateFilters();
    }, delay);
};

const onKeywordInput = () => {
    scheduleUpdateFilters(500);
};

const setSort = (field) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'desc';
    }
    updateFilters();
};

const sortIcon = (field) => {
    if (sortBy.value !== field) {
        return '↕';
    }
    return sortDir.value === 'asc' ? '▲' : '▼';
};

const hasFilters = computed(() => {
    return !!(
        filterJenis.value ||
        keyword.value ||
        filterAssignee.value ||
        filterPayment.value ||
        filterStatus.value
    );
});

const activeFilters = computed(() => {
    const out = [];
    if (keyword.value) {
        out.push({ key: 'search', label: `Cari: ${keyword.value}` });
    }
    if (filterJenis.value) {
        out.push({ key: 'jenis', label: `Jenis: ${props.jenisList[filterJenis.value] ?? filterJenis.value}` });
    }
    if (filterAssignee.value) {
        const assignee = props.assigneeList.find((row) => row.id === filterAssignee.value);
        out.push({ key: 'assignee', label: `Assignee: ${assignee?.name || filterAssignee.value}` });
    }
    if (filterPayment.value) {
        out.push({ key: 'payment', label: `Pembayaran: ${props.paymentList[filterPayment.value] ?? filterPayment.value}` });
    }
    if (filterStatus.value) {
        out.push({ key: 'status', label: `Status: ${filterStatus.value === 'closed' ? 'Closed' : 'Open'}` });
    }
    return out;
});

const removeFilter = (key) => {
    if (key === 'search') {
        keyword.value = '';
    }
    if (key === 'jenis') {
        filterJenis.value = '';
    }
    if (key === 'assignee') {
        filterAssignee.value = '';
    }
    if (key === 'payment') {
        filterPayment.value = '';
    }
    if (key === 'status') {
        filterStatus.value = '';
    }
    updateFilters();
};

watch([filterJenis, filterAssignee, filterPayment, filterStatus], () => {
    scheduleUpdateFilters(0);
});

watch(
    () => props.filters,
    (val) => {
        filterJenis.value = val.jenis || '';
        keyword.value = val.search || '';
        filterAssignee.value = val.assignee || '';
        filterPayment.value = val.payment || '';
        filterStatus.value = val.status || '';
        sortBy.value = val.sort_by || 'created_at';
        sortDir.value = val.sort_dir || 'desc';
    },
    { deep: true },
);

const toCurrency = (n) => `Rp ${Number(n || 0).toLocaleString('id-ID')}`;
const toCurrencyUsd = (n) => `US$ ${Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })}`;
const isFollowUp = (item) => {
    if (!item.deadline) return false;
    const due = new Date(item.deadline);
    const now = new Date();
    const limit = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 7);

    return due >= now && due <= limit;
};

const stageColor = (item) => {
    if (item.is_done) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    return 'bg-amber-50 text-amber-700 border-amber-200';
};

const paymentLabel = {
    belum: 'Belum Bayar',
    dp: 'DP',
    lunas: 'Lunas',
    _empty: 'Belum diatur',
};

const paymentSummary = computed(() => {
    const entries = Object.entries(props.summary.payment || {});
    return entries.length ? entries : [['_empty', 0]];
});

const toPercent = (n) => `${Number(n || 0).toFixed(1)}%`;
</script>

<template>
    <Layout title="CRM">
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">CRM</h1>
                <p class="text-brand-100 text-sm">Monitor pipeline lead, follow-up, dan nilai yang masih terbuka.</p>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Total Deal</p>
                    <p class="text-2xl font-bold text-slate-700 mt-1">{{ summary.total || 0 }}</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Open</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1">{{ summary.open || 0 }}</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Closed</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.deals || 0 }}</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Follow Up 7 Hari</p>
                    <p class="text-2xl font-bold text-brand-600 mt-1">{{ summary.dueSoon || 0 }}</p>
                </article>
            </section>

            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Conversion Rate</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">{{ toPercent(summary.conversion_rate) }}</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Open Rate</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1">{{ toPercent(summary.open_rate) }}</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Follow Up Rate</p>
                    <p class="text-2xl font-bold text-blue-700 mt-1">{{ toPercent(summary.dueSoonRate) }}</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Rata-rata Nilai/Deal</p>
                    <p class="text-xl font-bold text-slate-700 mt-1">{{ toCurrency(summary.avg_value || 0) }}</p>
                </article>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Nilai Deal</p>
                    <p class="text-xl font-bold text-slate-700 mt-1">{{ toCurrency(summary.value_idr || 0) }}</p>
                    <p class="text-xs text-slate-400 mt-1">
                        + {{ toCurrencyUsd(summary.value_usd || 0) }} (kurs {{ Number(rate || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 }) }})
                    </p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-slate-500">Distribusi Pembayaran</p>
                    <div class="mt-3 space-y-2 text-sm">
                        <div
                            v-for="([key, value], index) in paymentSummary"
                            :key="key || index"
                            class="flex items-center justify-between gap-2"
                        >
                            <span class="text-slate-600">{{ paymentLabel[key] || key }}</span>
                            <span class="text-brand-700 text-xs font-semibold px-2 py-0.5 rounded-full bg-brand-50">{{ value }} deal</span>
                        </div>
                    </div>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm lg:col-span-2">
                    <p class="text-xs text-slate-500">Distribusi per Assignee</p>
                    <div v-if="assigneeSummary.length === 0" class="text-xs text-slate-400 mt-2">Belum ada assignee.</div>
                    <div v-else class="space-y-2 mt-3">
                        <div v-for="r in assigneeSummary" :key="r.assignee" class="flex items-center justify-between text-sm">
                            <span class="text-slate-600">{{ r.assignee }}</span>
                            <span class="text-brand-700 text-xs font-semibold px-2 py-0.5 rounded-full bg-brand-50">{{ r.count }} deal ({{ r.open }} open)</span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                <div class="flex flex-wrap gap-3 mb-4">
                    <input
                        v-model="keyword"
                        type="text"
                        placeholder="Cari nama, kontak, catatan..."
                        @input="onKeywordInput"
                        @keyup.enter="updateFilters"
                        class="w-full md:w-80 border border-slate-200 rounded-xl px-3 py-2"
                    />
                    <select v-model="filterJenis" class="border border-slate-200 rounded-xl px-3 py-2">
                        <option value="">Semua Jenis</option>
                        <option v-for="(label, key) in jenisList" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <select v-model="filterStatus" class="border border-slate-200 rounded-xl px-3 py-2">
                        <option value="">Semua Status</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select v-model="filterAssignee" class="border border-slate-200 rounded-xl px-3 py-2">
                        <option value="">Semua Assignee</option>
                        <option v-for="user in assigneeList" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                    <select v-model="filterPayment" class="border border-slate-200 rounded-xl px-3 py-2">
                        <option value="">Semua Pembayaran</option>
                        <option v-for="(label, key) in paymentList" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <button
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm hover:bg-slate-50"
                        @click="clearFilters"
                    >
                        Reset
                    </button>
                </div>
                <div v-if="hasFilters" class="flex flex-wrap gap-2 mb-4">
                    <span
                        v-for="item in activeFilters"
                        :key="item.key"
                        class="inline-flex items-center gap-2 px-3 py-1 text-xs rounded-full bg-slate-100 text-slate-700 border border-slate-200"
                    >
                        {{ item.label }}
                        <button type="button" class="text-slate-500 hover:text-slate-700" @click="removeFilter(item.key)">×</button>
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-brand-100 bg-brand-50/60 text-left text-slate-600">
                            <th class="px-3 py-2.5 cursor-pointer select-none" @click="setSort('code')">
                                Kode
                                <span class="text-[11px] ml-1 text-slate-500">{{ sortIcon('code') }}</span>
                            </th>
                        <th class="px-3 py-2.5 cursor-pointer select-none" @click="setSort('name')">
                            Nama
                            <span class="text-[11px] ml-1 text-slate-500">{{ sortIcon('name') }}</span>
                        </th>
                        <th class="px-3 py-2.5 cursor-pointer select-none" @click="setSort('jenis')">
                            Jenis
                            <span class="text-[11px] ml-1 text-slate-500">{{ sortIcon('jenis') }}</span>
                        </th>
                        <th class="px-3 py-2.5 cursor-pointer select-none" @click="setSort('assignee')">
                            Assignee
                            <span class="text-[11px] ml-1 text-slate-500">{{ sortIcon('assignee') }}</span>
                        </th>
                        <th class="px-3 py-2.5 cursor-pointer select-none" @click="setSort('stage')">
                            Stage
                            <span class="text-[11px] ml-1 text-slate-500">{{ sortIcon('stage') }}</span>
                        </th>
                        <th class="px-3 py-2.5 cursor-pointer select-none" @click="setSort('deadline')">
                            Deadline
                            <span class="text-[11px] ml-1 text-slate-500">{{ sortIcon('deadline') }}</span>
                        </th>
                        <th class="px-3 py-2.5 cursor-pointer select-none" @click="setSort('nilai')">
                            Nilai
                            <span class="text-[11px] ml-1 text-slate-500">{{ sortIcon('nilai') }}</span>
                        </th>
                        <th class="px-3 py-2.5 cursor-pointer select-none" @click="setSort('status')">
                            Status
                            <span class="text-[11px] ml-1 text-slate-500">{{ sortIcon('status') }}</span>
                        </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="cards.length === 0">
                                <td colspan="8" class="text-center text-slate-400 py-10">Belum ada data CRM.</td>
                            </tr>
                            <tr v-for="item in cards" :key="item.id" class="border-b border-brand-50">
                                <td class="px-3 py-2.5 font-medium text-slate-700">{{ item.code }}</td>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-slate-700">{{ item.name }}</p>
                                    <p v-if="item.contacts.length" class="text-xs text-slate-400">
                                        <span v-for="(c, i) in item.contacts" :key="item.code + '-' + i">{{ i ? ' · ' : '' }}{{ c }}</span>
                                    </p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-brand-50 text-brand-700">{{ item.jenis || '-' }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ item.assignee || '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-600">
                                    <span :class="['text-xs px-2 py-0.5 rounded-full border', stageColor(item)]">{{ item.stage || '-' }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">
                                    <span
                                        v-if="item.deadline"
                                        :class="[
                                            'text-xs px-2 py-0.5 rounded-full border',
                                            isFollowUp(item) ? 'border-amber-300 text-amber-700 bg-amber-50' : 'border-slate-200',
                                        ]"
                                    >
                                        {{ item.deadline }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ toCurrency(item.amount_total_idr || 0) }}</td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                        :class="item.is_done ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                    >
                                        {{ item.is_done ? 'Closed' : 'Open' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </Layout>
</template>

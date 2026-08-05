<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Layout from '../Layout.vue';

const props = defineProps({
    reports: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const period = ref(props.filters.period || 'all');
const status = ref(props.filters.status || 'all');
const expanded = ref(null);

const periods = [
    { value: 'all', label: 'Semua' },
    { value: '7', label: '7 Hari' },
    { value: '30', label: '30 Hari' },
    { value: '90', label: '3 Bulan' },
];

const applyFilters = () => {
    const payload = {};
    if (period.value && period.value !== 'all') payload.period = period.value;
    if (status.value && status.value !== 'all') payload.status = status.value;
    router.get('/daily-report', payload, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    period.value = 'all';
    status.value = 'all';
    router.get('/daily-report', {}, { preserveState: false, preserveScroll: true });
};

const markRead = (notificationId) => {
    router.patch(`/notifications/${notificationId}/read`, {}, { preserveScroll: true });
};

const toDateTime = (value) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
};

const toRupiah = (n) => `Rp ${Number(n || 0).toLocaleString('id-ID')}`;
const toggleExpand = (id) => { expanded.value = expanded.value === id ? null : id; };
const reportStatus = (item) => ({
    label: item.read_at ? 'Sudah dibaca' : 'Belum dibaca',
    className: item.read_at ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200',
});

const filtersForPagination = computed(() => {
    const payload = {};
    if (period.value && period.value !== 'all') payload.period = period.value;
    if (status.value && status.value !== 'all') payload.status = status.value;
    return payload;
});
</script>

<template>
    <Layout title="Daily Report">
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">Daily Report</h1>
                <p class="text-brand-100 text-sm">Riwayat ringkasan harian Hermes — semua menu sekaligus.</p>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Filter bar: simple pill buttons -->
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="flex flex-wrap gap-3 items-center">
                    <div class="flex gap-1.5">
                        <button
                            v-for="p in periods" :key="p.value"
                            @click="period = p.value; applyFilters()"
                            :class="period === p.value ? 'bg-brand-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="px-3 py-1.5 rounded-full text-sm font-medium transition-all"
                        >{{ p.label }}</button>
                    </div>
                    <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>
                    <div class="flex gap-1.5">
                        <button
                            v-for="s in [{value:'all',label:'Semua'},{value:'unread',label:'Belum dibaca'},{value:'read',label:'Sudah dibaca'}]"
                            :key="s.value"
                            @click="status = s.value; applyFilters()"
                            :class="status === s.value ? 'bg-slate-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            class="px-3 py-1.5 rounded-full text-sm font-medium transition-all"
                        >{{ s.label }}</button>
                    </div>
                    <button v-if="period !== 'all' || status !== 'all'" @click="resetFilters" class="ml-auto text-xs text-slate-400 hover:text-slate-600 underline">
                        Reset filter
                    </button>
                </div>
            </section>

            <!-- Report list -->
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Tanggal</th>
                                <th class="px-4 py-2.5 font-semibold">Ringkasan</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                <th class="px-4 py-2.5 font-semibold">Dikirim</th>
                                <th class="px-4 py-2.5 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="row in reports.data" :key="row.id">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-700 font-semibold tabular-nums">{{ row.report_date || '-' }}</td>
                                    <td class="px-4 py-3 text-slate-500 max-w-[420px]">
                                        <p class="line-clamp-2 text-xs leading-relaxed">{{ row.message }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset', reportStatus(row).className]">
                                            {{ reportStatus(row).label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-500 text-xs">{{ toDateTime(row.created_at) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex gap-2 justify-end">
                                            <button v-if="!row.read_at" class="text-xs font-semibold px-2 py-1 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200" @click="markRead(row.id)">
                                                Tandai dibaca
                                            </button>
                                            <button class="text-xs font-semibold px-2 py-1 rounded-lg bg-brand-50 text-brand-700 hover:bg-brand-100" @click="toggleExpand(row.id)">
                                                {{ expanded === row.id ? 'Tutup' : 'Detail' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Detail expand row -->
                                <tr v-if="expanded === row.id">
                                    <td colspan="5" class="bg-slate-50 px-4 py-4">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-xs">
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Order</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.orders_created }} masuk</p>
                                                <p class="text-slate-500">{{ toRupiah(row.summary.orders_created_value_idr) }}</p>
                                                <p class="text-slate-500">{{ row.summary.orders_paid }} dibayar hari ini</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Absensi</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.absensi_hadir }} hadir</p>
                                                <p class="text-slate-500">{{ row.summary.absensi_izin_pending }} izin menunggu</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">CRM/Sales</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.crm_new }} lead baru</p>
                                                <p class="text-slate-500">{{ row.summary.crm_won }} won &middot; {{ row.summary.crm_due_soon }} jatuh tempo</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">OKR</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.okr_objectives }} objective</p>
                                                <p class="text-slate-500">{{ row.summary.okr_progress !== null ? row.summary.okr_progress + '% progres' : 'Belum ada data' }}</p>
                                                <p class="text-slate-500">{{ row.summary.okr_tercapai }} KR tercapai</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Kanban</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.kanban_active }} aktif</p>
                                                <p class="text-slate-500">{{ row.summary.kanban_done_today }} selesai hari ini</p>
                                                <p class="text-amber-600">{{ row.summary.kanban_overdue }} terlambat</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Pembukuan</p>
                                                <p class="font-semibold text-emerald-700">+{{ toRupiah(row.summary.pembukuan_in) }}</p>
                                                <p class="text-red-500">-{{ toRupiah(row.summary.pembukuan_out) }}</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Payroll</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.payroll_entries }} entri</p>
                                                <p class="text-slate-500">{{ toRupiah(row.summary.payroll_net) }}</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Insight</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.insight_konten }} konten</p>
                                                <p class="text-slate-500">{{ row.summary.insight_views }} views</p>
                                                <p class="text-slate-500">+{{ row.summary.insight_follower }} follower</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3 space-y-0.5">
                                                <p class="text-slate-400 font-semibold uppercase tracking-wide text-[10px]">Mindmap & Script</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.mindmap_updated }} mindmap</p>
                                                <p class="text-slate-500">{{ row.summary.script_new }} naskah baru</p>
                                            </article>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="reports.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-400">Belum ada Daily Report dari Hermes.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Pagination -->
            <div v-if="reports.last_page > 1" class="flex flex-wrap gap-1 justify-center">
                <button
                    v-for="page in reports.last_page" :key="page"
                    :class="page === reports.current_page ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'"
                    class="px-3 py-1 rounded-lg text-sm"
                    @click="router.get('/daily-report', { page, ...filtersForPagination }, { preserveState: true, preserveScroll: true })"
                    :disabled="page === reports.current_page"
                >{{ page }}</button>
            </div>
            <p v-if="reports.total !== undefined" class="text-xs text-slate-500 text-center">Total {{ reports.total }} laporan.</p>
        </div>
    </Layout>
</template>

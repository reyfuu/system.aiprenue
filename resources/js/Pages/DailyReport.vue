<script setup>
// Halaman Daily Report — list riwayat Hermes Daily Summary yang masuk ke notifikasi user.
import { ref } from 'vue';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Layout from '../Layout.vue';

const props = defineProps({
    reports: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const status = ref(props.filters.status || 'all');
const expanded = ref(null);
const filtersForPagination = computed(() => {
    const payload = {};

    if (from.value) {
        payload.from = from.value;
    }
    if (to.value) {
        payload.to = to.value;
    }
    if (status.value && status.value !== 'all') {
        payload.status = status.value;
    }

    return payload;
});

const applyFilters = () => {
    const payload = {};
    if (from.value) {
        payload.from = from.value;
    }
    if (to.value) {
        payload.to = to.value;
    }
    if (status.value && status.value !== 'all') {
        payload.status = status.value;
    }

    router.get('/daily-report', payload, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    from.value = '';
    to.value = '';
    status.value = 'all';
    router.get('/daily-report', {}, { preserveState: false, preserveScroll: true });
};

const markRead = (notificationId) => {
    router.patch(`/notifications/${notificationId}/read`, {}, { preserveScroll: true });
};

const toDateTime = (value) => {
    if (!value) {
        return '-';
    }
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const toRupiah = (n) => `Rp ${Number(n || 0).toLocaleString('id-ID')}`;

const toggleExpand = (id) => {
    expanded.value = expanded.value === id ? null : id;
};

const reportStatus = (item) => ({
    label: item.read_at ? 'Sudah dibaca' : 'Belum dibaca',
    className: item.read_at
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
        : 'bg-amber-50 text-amber-700 ring-amber-200',
});
</script>

<template>
    <Layout title="Daily Report">
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">Daily Report</h1>
                <p class="text-brand-100 text-sm">Riwayat kiriman ringkasan harian Hermes per hari.</p>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <label class="text-xs text-slate-500">
                        Dari
                        <input v-model="from" type="date" class="mt-1 block border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400" />
                    </label>
                    <label class="text-xs text-slate-500">
                        Sampai
                        <input v-model="to" type="date" class="mt-1 block border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400" />
                    </label>
                    <label class="text-xs text-slate-500">
                        Status
                        <select v-model="status" class="mt-1 block border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
                            <option value="all">Semua</option>
                            <option value="unread">Belum dibaca</option>
                            <option value="read">Sudah dibaca</option>
                        </select>
                    </label>
                    <div class="flex gap-2">
                        <button class="text-sm rounded-lg px-4 py-2 bg-brand-600 text-white hover:bg-brand-700" @click="applyFilters">Terapkan</button>
                        <button class="text-sm rounded-lg px-4 py-2 border border-slate-300 text-slate-600 hover:bg-slate-50" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Tanggal</th>
                                <th class="px-4 py-2.5 font-semibold">Judul</th>
                                <th class="px-4 py-2.5 font-semibold">Ringkas</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                <th class="px-4 py-2.5 font-semibold">Dikirim</th>
                                <th class="px-4 py-2.5 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="row in reports.data" :key="row.id">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-slate-700 tabular-nums">
                                        {{ row.report_date || '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 font-medium max-w-[260px] truncate">
                                        {{ row.title }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-500 max-w-[340px] truncate">
                                        {{ row.message }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset',
                                                reportStatus(row).className,
                                            ]"
                                        >
                                            {{ reportStatus(row).label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-slate-500">{{ toDateTime(row.created_at) }}</td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <div class="flex gap-2 justify-end">
                                            <button
                                                v-if="!row.read_at"
                                                class="text-xs font-semibold px-2 py-1 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200"
                                                @click="markRead(row.id)"
                                            >
                                                Tandai dibaca
                                            </button>
                                            <button
                                                class="text-xs font-semibold px-2 py-1 rounded-lg bg-brand-50 text-brand-700 hover:bg-brand-100"
                                                @click="toggleExpand(row.id)"
                                            >
                                                {{ expanded === row.id ? 'Sembunyikan' : 'Detail' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="expanded === row.id">
                                    <td colspan="6" class="bg-slate-50 px-4 py-3">
                                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                                            <article class="bg-white border border-brand-100 rounded-xl p-3">
                                                <p class="text-slate-500">Order baru</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.orders_created }} ({{ toRupiah(row.summary.orders_created_value_idr) }})</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3">
                                                <p class="text-slate-500">Order dibayar</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.orders_paid }} ({{ toRupiah(row.summary.orders_paid_value_idr) }})</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3">
                                                <p class="text-slate-500">Absensi</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.absensi_hadir }} hadir · {{ row.summary.absensi_izin_pending }} menunggu</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3">
                                                <p class="text-slate-500">CRM</p>
                                                <p class="font-semibold text-slate-700">
                                                    {{ row.summary.crm_new }} lead · {{ row.summary.crm_won }} won · {{ row.summary.crm_due_soon }} due ≤ 7 hari
                                                </p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3">
                                                <p class="text-slate-500">Payroll</p>
                                                <p class="font-semibold text-slate-700">{{ row.summary.payroll_entries }} entri · {{ toRupiah(row.summary.payroll_net) }}</p>
                                                <p class="text-slate-500 mt-1">Periode diupdate: {{ row.summary.payroll_periods_updated }}</p>
                                            </article>
                                            <article class="bg-white border border-brand-100 rounded-xl p-3">
                                                <p class="text-slate-500">Pembukuan</p>
                                                <p class="font-semibold text-slate-700">
                                                    +{{ toRupiah(row.summary.pembukuan_in) }} / -{{ toRupiah(row.summary.pembukuan_out) }}
                                                </p>
                                            </article>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="reports.data.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-slate-400">Belum ada Daily Report dari Hermes.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div v-if="reports.last_page > 1" class="flex flex-wrap gap-1 justify-center">
                <button
                    v-for="page in reports.last_page"
                    :key="page"
                    :class="
                        page === reports.current_page
                            ? 'bg-brand-600 text-white'
                            : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
                    "
                    class="px-3 py-1 rounded-lg text-sm"
                    @click="router.get('/daily-report', { page, ...filtersForPagination }, { preserveState: true, preserveScroll: true })"
                    :disabled="page === reports.current_page"
                >
                    {{ page }}
                </button>
            </div>

            <p v-if="reports.total !== undefined" class="text-xs text-slate-500 text-center">
                Total {{ reports.total }} item.
            </p>
        </div>
    </Layout>
</template>

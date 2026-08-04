<script setup>
// Halaman Payroll: lihat daftar hasil payroll per periode, generate ulang, dan finalize.
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Layout from '../../Layout.vue';

const props = defineProps({
    periods: { type: Array, default: () => [] },
    selectedPeriod: { type: String, default: '' },
    active: { type: Object, default: () => null },
    canManage: { type: Boolean, default: false },
    entries: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    preview: { type: Object, default: () => null },
});

const periodOptions = computed(() =>
    props.periods.map((p) => ({
        value: p.period,
        label: `${p.period} (${p.status === 'final' ? 'Final' : 'Draft'})`,
    })),
);

const sourceEntries = computed(() => (props.entries?.length ? props.entries : (props.preview?.entries || [])));
const sourceSummary = computed(() => (props.entries?.length ? props.summary : (props.preview?.summary || {})));
const showPreview = computed(() => !props.entries.length && !!props.preview);

const toCurrency = (n) => `Rp ${Number(n || 0).toLocaleString('id-ID')}`;

const formatMinutes = (m) => {
    const h = Math.floor((m || 0) / 60);
    const min = Math.max(0, (m || 0) % 60);
    return `${h}j ${min}m`;
};

const setPeriod = (value) => {
    router.get('/payroll', { period: value }, { preserveState: false, preserveScroll: true });
};

const generate = () => {
    if (!confirm(`Generate payroll untuk ${props.selectedPeriod}?`)) return;
    router.post('/payroll/generate', { period: props.selectedPeriod }, { preserveScroll: true });
};

const finalize = () => {
    if (!props.active?.id) return;
    if (!confirm('Setelah final, payroll tidak dapat diubah. Lanjutkan?')) return;
    router.patch(`/payroll/${props.active.id}/finalize`, {}, { preserveScroll: true });
};
</script>

<template>
    <Layout title="Payroll">
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">PAYROLL</h1>
                    <p class="text-brand-100 text-sm">Penggajian bulanan: hadir, telat, lembur, potongan, dan total bersih.</p>
                </div>
                <div class="flex items-center gap-2">
                    <select
                        :value="selectedPeriod"
                        class="bg-white border border-white/40 text-slate-700 rounded-xl px-3 py-2"
                        @change="setPeriod($event.target.value)"
                    >
                        <option v-if="!periodOptions.length" value="">Belum ada periode</option>
                        <option v-for="p in periodOptions" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <button
                        v-if="canManage"
                        class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition"
                        @click="generate"
                    >
                        Generate
                    </button>
                    <button
                        v-if="canManage && active && active.status !== 'final'"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition"
                        @click="finalize"
                    >
                        Finalisasi
                    </button>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-4">
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <article class="bg-white border border-brand-100 rounded-2xl p-4">
                    <p class="text-xs text-slate-500">Status</p>
                    <p v-if="active" class="mt-1 text-lg font-bold" :class="active.status === 'final' ? 'text-emerald-700' : 'text-amber-700'">
                        {{ active.status === 'final' ? 'Final' : 'Draft' }}
                    </p>
                    <p v-else class="mt-1 text-sm text-slate-500">Belum digenerate</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4">
                    <p class="text-xs text-slate-500">Total Karyawan</p>
                    <p class="mt-1 text-lg font-bold text-slate-700">{{ sourceEntries.length }}</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4">
                    <p class="text-xs text-slate-500">Gross Payroll</p>
                    <p class="mt-1 text-lg font-bold text-slate-700">{{ toCurrency(sourceSummary.gross_salary || 0) }}</p>
                </article>
                <article class="bg-white border border-brand-100 rounded-2xl p-4">
                    <p class="text-xs text-slate-500">Net Payroll</p>
                    <p class="mt-1 text-lg font-bold text-slate-700">{{ toCurrency(sourceSummary.net_salary || 0) }}</p>
                </article>
            </section>

            <div v-if="showPreview" class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl px-4 py-3 text-sm">
                <b>Preview:</b> periode ini belum ada record payroll di DB. Klik "Generate" untuk menyimpan.
            </div>

            <section class="bg-white border border-brand-100 rounded-2xl shadow-sm p-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-brand-100 bg-brand-50/60 text-left text-slate-600">
                                <th class="px-3 py-2.5">Karyawan</th>
                                <th class="px-3 py-2.5">Hari Kerja</th>
                                <th class="px-3 py-2.5">Hadir</th>
                                <th class="px-3 py-2.5">Telat</th>
                                <th class="px-3 py-2.5">Lembur</th>
                                <th class="px-3 py-2.5">Gaji Pokok</th>
                                <th class="px-3 py-2.5">Tunjangan</th>
                                <th class="px-3 py-2.5">Lembur</th>
                                <th class="px-3 py-2.5">Potongan</th>
                                <th class="px-3 py-2.5">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="sourceEntries.length === 0">
                                <td colspan="10" class="text-center text-slate-400 py-8">Belum ada data payroll.</td>
                            </tr>
                            <tr v-for="item in sourceEntries" :key="item.id || item.user_id" class="border-b border-brand-50">
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-slate-700">{{ item.user || item.name }}</p>
                                    <p class="text-xs text-slate-400">{{ item.notes || '' }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ item.work_days }}</td>
                                <td class="px-3 py-2.5 text-slate-600">
                                    {{ item.attendance_days }} / {{ item.absent_days === 0 ? '0' : item.absent_days }}
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ formatMinutes(item.late_minutes) }}</td>
                                <td class="px-3 py-2.5 text-slate-600">{{ formatMinutes(item.overtime_minutes) }}</td>
                                <td class="px-3 py-2.5 text-slate-600">{{ toCurrency(item.base_salary || 0) }}</td>
                                <td class="px-3 py-2.5 text-slate-600">{{ toCurrency(item.allowance || 0) }}</td>
                                <td class="px-3 py-2.5 text-slate-600">
                                    {{ toCurrency(item.overtime_amount || 0) }}
                                    <span class="text-[11px] text-slate-400">({{ Number(item.overtime_rate || 0).toLocaleString('id-ID') }}/jam)</span>
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ toCurrency(item.deductions || 0) }}</td>
                                <td class="px-3 py-2.5 font-semibold text-emerald-700">{{ toCurrency(item.net_salary || 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </Layout>
</template>

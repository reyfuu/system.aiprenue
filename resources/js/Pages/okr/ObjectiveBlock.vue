<script setup>
// Satu Objective bernomor + daftar Key Result-nya. Judul & keterangan diubah
// INLINE (klik); tambah KR membuka KrEditor inline (bukan modal).
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import InlineEdit from './InlineEdit.vue';
import KeyResultRow from './KeyResultRow.vue';
import KrEditor from './KrEditor.vue';
import { barWidth, barColor, statusText, statusLabel } from './helpers.js';

const props = defineProps({
    objective: { type: Object, required: true },
    index: { type: Number, required: true },        // 0-based → nomor 01, 02…
    quarter: { type: Object, required: true },       // { year, quarter } untuk payload
    canManage: { type: Boolean, default: false },
    kartuTersedia: { type: Array, default: () => [] },
    sources: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
    units: { type: Object, default: () => ({}) },
});

const tambahKr = ref(false);

// Update objective: server memvalidasi year/quarter/title. Kirim set lengkap
// dengan nilai yg tak diubah tetap dari objective sekarang.
const simpanObjektif = (patch) => router.put(`/okr/objectives/${props.objective.id}`, {
    year: props.quarter.year, quarter: props.quarter.quarter,
    title: patch.title ?? props.objective.title,
    description: patch.description ?? props.objective.description ?? '',
}, { preserveScroll: true });

const hapus = () => {
    if (confirm(`Hapus Objective "${props.objective.title}"? Semua Key Result di dalamnya ikut terhapus.`)) {
        router.delete(`/okr/objectives/${props.objective.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <section class="py-8 border-b border-slate-100 grid sm:grid-cols-[130px_1fr] gap-4 sm:gap-10 group/obj">
        <!-- Margin kiri: nomor + status + garis progres -->
        <div class="flex sm:block items-center gap-4">
            <div class="text-5xl font-medium text-slate-800 tabular-nums leading-none">{{ (index + 1).toString().padStart(2, '0') }}</div>
            <div class="sm:mt-3">
                <div class="font-sans text-[11px] uppercase tracking-wider font-bold" :class="statusText(objective.progress)">
                    {{ statusLabel(objective.progress) }} · {{ objective.progress === null ? '—' : objective.progress + '%' }}
                </div>
                <div class="hidden sm:block h-[2px] bg-slate-200 mt-2.5 relative overflow-hidden">
                    <div class="absolute inset-y-0 left-0" :class="barColor(objective.progress)" :style="{ width: barWidth(objective.progress) }"></div>
                </div>
            </div>
        </div>

        <!-- Konten -->
        <div class="min-w-0">
            <div class="flex items-baseline justify-between gap-4">
                <InlineEdit :model-value="objective.title" :editable="canManage" @save="(v) => simpanObjektif({ title: v })"
                            input-class="w-full border border-slate-200 rounded px-1.5 py-0.5 text-2xl sm:text-[28px] font-semibold text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                    <h2 class="text-2xl sm:text-[28px] font-semibold text-slate-800 tracking-tight leading-tight max-w-[22ch] text-balance">{{ objective.title }}</h2>
                </InlineEdit>
                <div class="flex items-baseline gap-2 shrink-0">
                    <div class="font-sans text-2xl font-bold text-slate-800 tabular-nums">{{ objective.progress === null ? '—' : objective.progress + '%' }}</div>
                    <button v-if="canManage" type="button" class="font-sans text-slate-300 hover:text-red-600 opacity-0 group-hover/obj:opacity-100 transition self-center" title="Hapus Objective" @click="hapus">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Keterangan: klik untuk ubah; placeholder halus bila kosong -->
            <InlineEdit :model-value="objective.description ?? ''" type="textarea" :editable="canManage" @save="(v) => simpanObjektif({ description: v })"
                        input-class="w-full mt-2 border border-slate-200 rounded-lg px-2 py-1.5 text-sm text-slate-600 bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                <p v-if="objective.description" class="text-slate-500 mt-2 max-w-prose">{{ objective.description }}</p>
                <p v-else-if="canManage" class="font-sans text-slate-300 text-sm mt-2 italic">+ Tambah keterangan</p>
            </InlineEdit>

            <!-- Daftar Key Result -->
            <div class="mt-5">
                <KeyResultRow v-for="kr in objective.key_results" :key="kr.id"
                              :kr="kr" :objective="objective" :can-manage="canManage" :kartu-tersedia="kartuTersedia"
                              :sources="sources" :metrics="metrics" :units="units" />
            </div>

            <!-- Tambah Key Result inline -->
            <template v-if="canManage">
                <KrEditor v-if="tambahKr" :objective="objective" :sources="sources" :metrics="metrics" :units="units" @done="tambahKr = false" />
                <button v-else type="button" class="font-sans mt-3 text-sm font-semibold text-brand-700 hover:underline" @click="tambahKr = true">
                    + Key Result
                </button>
            </template>
        </div>
    </section>
</template>

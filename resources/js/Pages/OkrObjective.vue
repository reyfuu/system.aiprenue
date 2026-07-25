<script setup>
// Halaman DETAIL satu Objective (dibuka dari kartu di dashboard OKR).
// Di sinilah semua penyuntingan: judul/divisi/keterangan objective (inline),
// dan Key Result + langkah (pakai ulang komponen okr/*). Dashboard sendiri
// hanya ringkasan + navigasi.
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import InlineEdit from './okr/InlineEdit.vue';
import KeyResultRow from './okr/KeyResultRow.vue';
import KrEditor from './okr/KrEditor.vue';
import { barWidth, barColor, statusText, statusLabel } from './okr/helpers.js';

const props = defineProps({
    objective: { type: Object, required: true },
    quarter: { type: Object, required: true },
    metrics: { type: Object, default: () => ({}) },
    sources: { type: Object, default: () => ({}) },
    units: { type: Object, default: () => ({}) },
    kartuTersedia: { type: Array, default: () => [] },
    canManage: Boolean,
});

const tambahKr = ref(false);

// Update objective: server memvalidasi year/quarter/title. Kirim set lengkap,
// nilai tak diubah tetap dari objective sekarang.
const simpanObjektif = (patch, opts = {}) => router.put(`/okr/objectives/${props.objective.id}`, {
    year: props.quarter.year, quarter: props.quarter.quarter,
    title: patch.title ?? props.objective.title,
    description: patch.description ?? props.objective.description ?? '',
    division: patch.division ?? props.objective.division ?? null,
}, { preserveScroll: true, ...opts });

// ---- Panel "Ubah objective" ----
//
//  Ubah-di-tempat (klik judul/keterangan) tetap ada dan tetap jalur tercepat,
//  tapi ia tak terlihat sebagai tombol — orang yang belum tahu bisa mengira
//  halaman ini cuma bacaan. Panel ini pintu yang kelihatan: ketiga kolom
//  (judul, divisi, keterangan) sekaligus dalam satu simpan.
const panelUbah = ref(false);
const form = ref({ title: '', division: '', description: '' });

const mulaiUbah = () => {
    form.value = {
        title: props.objective.title,
        division: props.objective.division ?? '',
        description: props.objective.description ?? '',
    };
    panelUbah.value = true;
};

const simpanPanel = () => {
    if (!form.value.title.trim()) return;
    simpanObjektif(
        { title: form.value.title, division: form.value.division || null, description: form.value.description },
        { onSuccess: () => { panelUbah.value = false; } },
    );
};

// Hapus objective → controller mengarahkan ke dashboard kuartal yang sama.
const hapus = () => {
    if (confirm(`Hapus Objective "${props.objective.title}"? Semua Key Result di dalamnya ikut terhapus.`)) {
        router.delete(`/okr/objectives/${props.objective.id}`);
    }
};
</script>

<template>
    <Layout title="OKR — Objective">
        <!-- Header brand + tombol kembali ke dashboard kuartal yang sama -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <Link :href="`/okr?q=${quarter.key}`" class="inline-flex items-center gap-1.5 text-brand-100 hover:text-white text-sm font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M11 6l-6 6 6 6"/></svg>
                    Kembali ke OKR
                </Link>
                <p class="text-brand-100 text-xs mt-2">{{ quarter.label }}</p>
                <h1 class="text-2xl font-bold tracking-tight mt-0.5">Detail Objective</h1>
            </div>
        </header>

        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <!-- Kepala objective -->
            <section class="bg-white border border-brand-100 rounded-2xl shadow-sm p-6">
                <!-- Mode UBAH: satu formulir untuk judul, divisi, keterangan -->
                <form v-if="canManage && panelUbah" class="space-y-3" @submit.prevent="simpanPanel">
                    <label class="block text-xs font-semibold text-slate-500">
                        <span class="block mb-1">Judul objective</span>
                        <input v-model="form.title" type="text" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-lg font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-300" />
                    </label>
                    <label class="block text-xs font-semibold text-slate-500">
                        <span class="block mb-1">Divisi</span>
                        <input v-model="form.division" type="text" placeholder="mis. Growth" class="w-56 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-300" />
                    </label>
                    <label class="block text-xs font-semibold text-slate-500">
                        <span class="block mb-1">Keterangan</span>
                        <textarea v-model="form.description" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-brand-300"></textarea>
                    </label>
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg px-4 py-2">Simpan</button>
                        <button type="button" class="text-sm font-semibold text-slate-400 hover:text-slate-600" @click="panelUbah = false">Batal</button>
                        <button type="button" class="ml-auto text-sm font-semibold text-slate-400 hover:text-red-600" @click="hapus">Hapus objective</button>
                    </div>
                </form>

                <!-- Mode BACA (ubah-di-tempat tetap aktif per kolom) -->
                <div v-else class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-bold" :class="statusText(objective.progress)">
                                {{ statusLabel(objective.progress) }} · {{ objective.progress === null ? '—' : objective.progress + '%' }}
                            </span>
                            <InlineEdit v-if="canManage || objective.division" :model-value="objective.division ?? ''" :editable="canManage" @save="(v) => simpanObjektif({ division: v })"
                                        input-class="w-40 border border-slate-200 rounded px-1.5 py-0.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                                <span v-if="objective.division" class="text-[11px] px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 font-semibold">{{ objective.division }}</span>
                                <span v-else-if="canManage" class="text-[11px] text-slate-300 italic">+ divisi</span>
                            </InlineEdit>
                        </div>

                        <InlineEdit :model-value="objective.title" :editable="canManage" @save="(v) => simpanObjektif({ title: v })"
                                    input-class="w-full border border-slate-200 rounded px-2 py-1 text-2xl font-bold text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <h2 class="text-2xl font-bold text-slate-800 leading-tight">{{ objective.title }}</h2>
                        </InlineEdit>

                        <InlineEdit :model-value="objective.description ?? ''" type="textarea" :editable="canManage" @save="(v) => simpanObjektif({ description: v })"
                                    input-class="w-full mt-2 border border-slate-200 rounded-lg px-2 py-1.5 text-sm text-slate-600 bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <p v-if="objective.description" class="text-slate-500 mt-2">{{ objective.description }}</p>
                            <p v-else-if="canManage" class="text-slate-300 text-sm mt-2 italic">+ Tambah keterangan</p>
                        </InlineEdit>

                        <p v-if="objective.created_by_name" class="text-xs text-slate-400 mt-3">PJ · {{ objective.created_by_name }}</p>
                    </div>

                    <!-- Progress besar + aksi (ubah / hapus) -->
                    <div class="text-right shrink-0">
                        <div class="text-4xl font-bold text-brand-700 tabular-nums">{{ objective.progress === null ? '—' : objective.progress + '%' }}</div>
                        <div class="w-32 h-2.5 bg-slate-100 rounded-full overflow-hidden mt-2">
                            <div :class="['h-full rounded-full', barColor(objective.progress)]" :style="{ width: barWidth(objective.progress) }"></div>
                        </div>
                        <div v-if="canManage" class="flex items-center justify-end gap-3 mt-3">
                            <button type="button" class="text-xs font-semibold text-slate-400 hover:text-brand-700" @click="mulaiUbah">Ubah</button>
                            <button type="button" class="text-xs font-semibold text-slate-400 hover:text-red-600" @click="hapus">Hapus</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Key Results (editing inline penuh) -->
            <section class="bg-white border border-brand-100 rounded-2xl shadow-sm p-6">
                <h3 class="text-sm uppercase tracking-widest text-slate-400 font-semibold mb-2">Key Results</h3>

                <p v-if="!objective.key_results.length" class="text-sm text-slate-400 py-4">Belum ada Key Result.</p>

                <KeyResultRow v-for="kr in objective.key_results" :key="kr.id"
                              :kr="kr" :objective="objective" :can-manage="canManage" :kartu-tersedia="kartuTersedia"
                              :sources="sources" :metrics="metrics" :units="units" />

                <template v-if="canManage">
                    <KrEditor v-if="tambahKr" :objective="objective" :sources="sources" :metrics="metrics" :units="units" @done="tambahKr = false" />
                    <button v-else type="button" class="mt-4 text-sm font-semibold text-brand-700 hover:underline" @click="tambahKr = true">
                        + Key Result
                    </button>
                </template>
            </section>
        </div>
    </Layout>
</template>

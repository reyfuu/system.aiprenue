<script setup>
// Satu baris Key Result. Semua aksi INLINE (nol modal):
//   • judul       → klik untuk ubah (InlineEdit)
//   • realisasi manual → klik angkanya untuk ubah
//   • struktur (sumber/metrik/target/satuan) → tombol "ubah" membuka KrEditor
//   • kartu langkah → tambah/tautkan/lepas langsung di baris (tanpa toggle panel)
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import InlineEdit from './InlineEdit.vue';
import KrEditor from './KrEditor.vue';
import { fmt, fmtFull, barWidth, barColor, statusText, sumberAuto } from './helpers.js';

const props = defineProps({
    kr: { type: Object, required: true },
    objective: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    kartuTersedia: { type: Array, default: () => [] },
    sources: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
    units: { type: Object, default: () => ({}) },
});

const ubahStruktur = ref(false);           // panel KrEditor terbuka?
const endorseBaru = ref('');               // input kartu langkah baru
const deadlineBaru = ref('');
const attachId = ref('');                  // pilih kartu yg sudah ada utk ditautkan

const jumlahSelesai = () => props.kr.kartu.filter((k) => k.selesai).length;

// Ubah judul KR: kirim set lengkap (server memvalidasi source/target/unit).
const simpanJudul = (judul) => router.put(`/okr/key-results/${props.kr.id}`, {
    objective_id: props.objective.id, title: judul, source: props.kr.source,
    metric: props.kr.metric, target: props.kr.target, unit: props.kr.unit,
}, { preserveScroll: true });

// Ubah realisasi manual dgn klik angkanya — pengganti modal "Perbarui angka".
const simpanActual = (nilai) => router.patch(`/okr/key-results/${props.kr.id}/actual`, {
    actual_manual: nilai,
}, { preserveScroll: true });

const hapus = () => {
    if (confirm(`Hapus Key Result "${props.kr.title}"?`)) {
        router.delete(`/okr/key-results/${props.kr.id}`, { preserveScroll: true });
    }
};

// ---- Kartu langkah (KR bersumber 'kartu') ----
const tambahKartu = () => {
    if (!endorseBaru.value.trim()) return;
    router.post(`/okr/key-results/${props.kr.id}/kartu`, { endorse: endorseBaru.value, deadline: deadlineBaru.value || null }, {
        preserveScroll: true,
        onSuccess: () => { endorseBaru.value = ''; deadlineBaru.value = ''; },
    });
};
const tautkan = () => {
    if (!attachId.value) return;
    router.post(`/okr/key-results/${props.kr.id}/attach`, { pipeline_id: attachId.value }, {
        preserveScroll: true, onSuccess: () => { attachId.value = ''; },
    });
};
const lepas = (kartuId) => router.delete(`/okr/key-results/${props.kr.id}/kartu/${kartuId}`, { preserveScroll: true });
</script>

<template>
    <div class="grid sm:grid-cols-[1fr_auto] gap-x-6 gap-y-2 py-4 border-t border-slate-100 first:border-slate-200 group/kr">
        <!-- Kiri: judul + sumber + langkah kartu -->
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <InlineEdit :model-value="kr.title" :editable="canManage" @save="simpanJudul"
                            input-class="w-full border border-slate-200 rounded px-1.5 py-0.5 text-[19px] bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                    <span class="text-[19px] text-slate-800">{{ kr.title }}</span>
                </InlineEdit>
                <button v-if="canManage" type="button" class="text-slate-300 hover:text-red-600 opacity-0 group-hover/kr:opacity-100 transition" title="Hapus Key Result" @click="hapus">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="font-sans text-[12.5px] text-slate-400 mt-1.5 flex items-center gap-2.5 flex-wrap">
                <span class="text-[10.5px] font-bold uppercase tracking-wide px-1.5 py-0.5 border rounded"
                      :class="kr.source === 'auto' ? 'text-brand-600 border-brand-200' : 'text-slate-400 border-slate-200'">
                    {{ kr.source === 'auto' ? sumberAuto(kr) : kr.source_label }}
                </span>
                <span v-if="kr.source === 'kartu'">{{ jumlahSelesai() }} dari {{ kr.kartu.length }} kartu selesai</span>
                <span v-if="kr.owner_name && kr.source !== 'kartu'">PJ: {{ kr.owner_name }}</span>
                <span v-if="kr.percent !== null && kr.percent < 60" class="inline-flex items-center gap-1 text-red-700 font-semibold">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    berisiko
                </span>
                <button v-if="canManage" type="button" class="text-slate-400 hover:text-brand-700 font-semibold" @click="ubahStruktur = !ubahStruktur">
                    {{ ubahStruktur ? 'tutup' : 'ubah' }}
                </button>
            </div>

            <!-- Editor struktur (sumber/metrik/target/satuan) -->
            <KrEditor v-if="ubahStruktur" :objective="objective" :kr="kr" :sources="sources" :metrics="metrics" :units="units" @done="ubahStruktur = false" />

            <!-- Langkah: kartu todolist tertaut. Jembatan goal → papan kerja.
                 Dikelola DI SINI (Kanban murni delegasi). -->
            <template v-if="kr.source === 'kartu'">
                <ul v-if="kr.kartu.length" class="font-sans mt-2.5 space-y-1.5">
                    <li v-for="k in kr.kartu" :key="k.id" class="group/step flex items-center gap-2 text-[12.5px]">
                        <svg v-if="k.selesai" class="w-3.5 h-3.5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 flex-shrink-0"></span>
                        <span :class="k.selesai ? 'text-slate-400 line-through' : 'text-slate-600'">{{ k.judul }}</span>
                        <span v-if="k.ketepatan === 'terlambat'" class="text-red-700 font-semibold">· telat</span>
                        <button v-if="canManage" type="button" class="ml-auto text-slate-300 hover:text-red-600 opacity-0 group-hover/step:opacity-100 transition" title="Lepas dari goal ini" @click="lepas(k.id)">lepas</button>
                    </li>
                </ul>

                <!-- Tambah langkah langsung — tanpa toggle panel. -->
                <div v-if="canManage" class="font-sans mt-2 space-y-1.5">
                    <form class="flex flex-wrap items-center gap-1.5" @submit.prevent="tambahKartu">
                        <input v-model="endorseBaru" type="text" placeholder="+ Tambah langkah…" class="flex-1 min-w-[150px] border border-slate-200 rounded-lg px-2 py-1 text-[12px] focus:outline-none focus:ring-2 focus:ring-brand-300" />
                        <input v-model="deadlineBaru" type="date" title="Deadline (opsional)" class="border border-slate-200 rounded-lg px-2 py-1 text-[12px] text-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-300" />
                        <button type="submit" class="text-[12px] font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg px-2.5 py-1">Buat</button>
                    </form>
                    <div v-if="kartuTersedia.length" class="flex items-center gap-1.5">
                        <select v-model="attachId" class="flex-1 border border-slate-200 rounded-lg px-2 py-1 text-[12px] bg-white text-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <option value="">Tautkan kartu yang sudah ada…</option>
                            <option v-for="c in kartuTersedia" :key="c.id" :value="c.id">{{ c.judul }}</option>
                        </select>
                        <button type="button" :disabled="!attachId" class="text-[12px] font-semibold text-brand-700 hover:underline disabled:opacity-40" @click="tautkan">Tautkan</button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Kanan: figur, meter, persen -->
        <div class="sm:text-right sm:min-w-[180px]">
            <div class="font-sans text-[13px] text-slate-400" :title="fmtFull(kr.actual, kr.unit) + ' dari ' + fmtFull(kr.target, kr.unit)">
                <!-- Realisasi manual bisa diklik untuk diubah; auto/kartu hanya baca -->
                <InlineEdit v-if="kr.source === 'manual' && canManage" :model-value="kr.actual" type="number" @save="simpanActual"
                            input-class="w-24 border border-slate-200 rounded px-1.5 py-0.5 text-sm text-right bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                    <b class="text-slate-700 underline decoration-dotted decoration-slate-300 underline-offset-2">{{ fmt(kr.actual, kr.unit) }}</b>
                </InlineEdit>
                <b v-else class="text-slate-700">{{ fmt(kr.actual, kr.unit) }}</b>
                / {{ kr.target > 0 ? fmt(kr.target, kr.unit) : '—' }}
            </div>
            <div class="flex items-center gap-2.5 mt-1.5 sm:justify-end">
                <div class="h-[3px] w-24 bg-slate-100 relative overflow-hidden">
                    <div class="absolute inset-y-0 left-0" :class="barColor(kr.percent)" :style="{ width: barWidth(kr.percent) }"></div>
                </div>
                <div class="font-sans text-xl font-bold tabular-nums" :class="statusText(kr.percent)">
                    {{ kr.percent === null ? '—' : kr.percent + '%' }}
                </div>
            </div>
        </div>
    </div>
</template>

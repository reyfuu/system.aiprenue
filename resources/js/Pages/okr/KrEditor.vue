<script setup>
// Form Key Result ringkas INLINE (menggantikan modal). Dipakai untuk membuat KR
// baru (kr=null) maupun mengubah struktur KR yang ada (sumber/metrik/target/
// satuan). Judul KR yang sudah ada diubah lewat InlineEdit di baris; editor ini
// untuk hal yang butuh beberapa pilihan sekaligus.
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    objective: { type: Object, required: true },
    kr: { type: Object, default: null },     // null = buat baru
    sources: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
    units: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['done']);

const f = ref({
    title: props.kr?.title ?? '',
    source: props.kr?.source ?? 'manual',
    metric: props.kr?.metric ?? '',
    target: props.kr?.target ?? 0,
    unit: props.kr?.unit ?? 'angka',
});
const err = ref({});
const processing = ref(false);

const simpan = () => {
    processing.value = true;
    err.value = {};
    // Selalu kirim set lengkap — validasi server menuntut source/target/unit,
    // dan required_if metric saat auto. Server yang membereskan kolom tak
    // terpakai per sumber (mis. kartu → unit dipaksa 'angka').
    const payload = {
        objective_id: props.objective.id,
        title: f.value.title,
        source: f.value.source,
        metric: f.value.metric,
        target: f.value.target,
        unit: f.value.unit,
    };
    const opts = {
        preserveScroll: true,
        onError: (e) => { err.value = e; },
        onSuccess: () => emit('done'),
        onFinish: () => { processing.value = false; },
    };
    props.kr ? router.put(`/okr/key-results/${props.kr.id}`, payload, opts) : router.post('/okr/key-results', payload, opts);
};
</script>

<template>
    <form class="font-sans mt-3 rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-2.5" @submit.prevent="simpan">
        <div>
            <input v-model="f.title" type="text" placeholder="Judul Key Result — mis. Total view seluruh konten"
                   class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-300" />
            <p v-if="err.title" class="text-[11px] text-red-600 mt-1">{{ err.title }}</p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <!-- Sumber angka -->
            <label class="text-xs text-slate-500">
                <span class="block mb-0.5">Sumber</span>
                <select v-model="f.source" class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                    <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                </select>
            </label>
            <!-- Metrik: wajib & hanya relevan saat auto -->
            <label v-if="f.source === 'auto'" class="text-xs text-slate-500">
                <span class="block mb-0.5">Metrik</span>
                <select v-model="f.metric" class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                    <option value="">— pilih —</option>
                    <option v-for="(label, key) in metrics" :key="key" :value="key">{{ label }}</option>
                </select>
            </label>
            <!-- Target -->
            <label class="text-xs text-slate-500">
                <span class="block mb-0.5">{{ f.source === 'kartu' ? 'Target (kartu)' : 'Target' }}</span>
                <input v-model="f.target" type="number" min="0" :step="f.source === 'kartu' ? 1 : 'any'"
                       class="w-28 border border-slate-200 rounded-lg px-2 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-300" />
            </label>
            <!-- Satuan: KR kartu selalu 'angka', pemilih disembunyikan -->
            <label v-if="f.source !== 'kartu'" class="text-xs text-slate-500">
                <span class="block mb-0.5">Satuan</span>
                <select v-model="f.unit" class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                    <option v-for="(label, key) in units" :key="key" :value="key">{{ label }}</option>
                </select>
            </label>
        </div>

        <p class="text-[11px] text-slate-400">
            {{ f.source === 'auto'
                ? 'Realisasi dihitung sendiri dari Insight/Pembukuan — tak bisa diisi manual.'
                : f.source === 'kartu'
                ? 'Realisasi = kartu todolist tertaut yang selesai. Kartunya dibuat & ditautkan di baris ini.'
                : 'Realisasi diperbarui dengan mengklik angkanya langsung.' }}
        </p>
        <p v-if="err.metric" class="text-[11px] text-red-600">{{ err.metric }}</p>
        <p v-if="err.target" class="text-[11px] text-red-600">{{ err.target }}</p>

        <div class="flex items-center gap-3 pt-0.5">
            <button type="submit" :disabled="processing" class="text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg px-3 py-1.5 disabled:opacity-50">
                {{ kr ? 'Simpan' : 'Tambah Key Result' }}
            </button>
            <button type="button" class="text-sm font-semibold text-slate-400 hover:text-slate-600" @click="emit('done')">Batal</button>
        </div>
    </form>
</template>

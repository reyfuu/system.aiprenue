<script setup>
// Halaman Content: kalender produksi berbentuk tabel lebar, filter minggu,
// serta modal tambah/edit. Kolom mengikuti spreadsheet operasional tim.
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Layout from '../../Layout.vue';
import ModalWrap from '../../ModalWrap.vue';

const props = defineProps({
    contents: Object,          // paginator Laravel
    filters: Object,           // { week: YYYY-Www }
    progressOptions: Object,   // key → label progress
    canManageContent: Boolean, // izin CRUD khusus Content dari Manajemen Akses
});

const canManage = computed(() => props.canManageContent);
const week = ref(props.filters.week || '');

// Filter dijalankan server-side agar pagination tetap menghitung minggu yang benar.
const applyWeek = () => router.get('/content', { week: week.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

const fields = [
    'comp', 'jenis_postingan', 'kategori', 'referensi', 'inti_pesan',
    'hook_material', 'brief_original', 'opsi_brief', 'script_remake',
    'editor', 'progress', 'tanggal_upload', 'link_hasil_editing',
    'link_b_roll', 'caption', 'link_ai_kata_kunci',
];

const blank = () => ({
    comp: '', jenis_postingan: '', kategori: '', referensi: '', inti_pesan: '',
    hook_material: '', brief_original: '', opsi_brief: '', script_remake: '',
    editor: '', progress: 'draft', tanggal_upload: '', link_hasil_editing: '',
    link_b_roll: '', caption: '', link_ai_kata_kunci: '',
});

const open = ref(false);
const editId = ref(null);
const form = useForm(blank());

// Tambah selalu dimulai dari form kosong; edit menyalin hanya field yang dikenal.
const openCreate = () => {
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    editId.value = null;
    open.value = true;
};

const openEdit = (content) => {
    form.clearErrors();
    fields.forEach((field) => {
        form[field] = content[field] ?? '';
    });
    form.tanggal_upload = content.tanggal_upload?.substring(0, 10) || '';
    editId.value = content.id;
    open.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => { open.value = false; },
    };

    editId.value ? form.put('/content/' + editId.value, options) : form.post('/content', options);
};

const destroyContent = (content) => {
    if (confirm('Yakin hapus content ini? Tindakan ini tidak bisa dibatalkan.')) {
        router.delete('/content/' + content.id, { preserveScroll: true });
    }
};

const fmtDate = (value) => value
    ? new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
    : '—';
const isUrl = (value) => /^https?:\/\//i.test(value || '');
const progressClass = (value) => ({
    draft: 'bg-slate-100 text-slate-600', script: 'bg-violet-100 text-violet-700',
    editing: 'bg-blue-100 text-blue-700', review: 'bg-amber-100 text-amber-700',
    scheduled: 'bg-cyan-100 text-cyan-700', published: 'bg-emerald-100 text-emerald-700',
}[value] || 'bg-slate-100 text-slate-600');
</script>

<template>
    <Layout title="Content">
        <!-- Header modul dan aksi tambah yang mengikuti izin mutasi server. -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">CONTENT</h1>
                    <p class="text-brand-100 text-sm">Kalender produksi dan materi postingan</p>
                </div>
                <button v-if="canManage" @click="openCreate" class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow transition">
                    + Tambah Content
                </button>
            </div>
        </header>

        <div class="px-6 py-6">
            <!-- Filter minggu ISO. Nilai kosong menampilkan seluruh data. -->
            <div class="bg-white border border-brand-100 shadow-sm rounded-2xl p-4 mb-5 flex flex-wrap items-end gap-3">
                <label class="text-sm font-medium text-slate-600">
                    Filter minggu upload
                    <input v-model="week" type="week" @change="applyWeek" class="mt-1 block border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <button v-if="week" @click="week = ''; applyWeek()" class="px-4 py-2 text-sm text-slate-600 hover:text-brand-700">Tampilkan semua</button>
                <span class="ml-auto text-xs text-slate-400">{{ contents.total }} content</span>
            </div>

            <!-- Tabel sengaja horizontal-scroll: materi panjang tidak dipaksa masuk viewport. -->
            <div class="bg-white border border-brand-100 shadow-sm rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-[2600px] w-full text-xs text-left">
                        <thead class="bg-brand-50 text-slate-700 uppercase">
                            <tr>
                                <th class="px-3 py-3">Comp</th><th class="px-3 py-3">Jenis Postingan</th><th class="px-3 py-3">Kategori</th>
                                <th class="px-3 py-3">Referensi</th><th class="px-3 py-3">Inti Pesan</th><th class="px-3 py-3">Hook &amp; Material</th>
                                <th class="px-3 py-3">Brief Original</th><th class="px-3 py-3">Opsi Brief/Fiks</th><th class="px-3 py-3">Script Remake</th>
                                <th class="px-3 py-3">Editor</th><th class="px-3 py-3">Progress</th><th class="px-3 py-3">Tanggal Upload</th>
                                <th class="px-3 py-3">Link Hasil Editing</th><th class="px-3 py-3">Link B Roll</th><th class="px-3 py-3">Caption</th>
                                <th class="px-3 py-3">Link AI + Kata Kunci</th><th v-if="canManage" class="px-3 py-3 sticky right-0 bg-brand-50">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!contents.data.length"><td :colspan="canManage ? 17 : 16" class="px-4 py-12 text-center text-slate-400">Belum ada content pada filter ini.</td></tr>
                            <tr v-for="content in contents.data" :key="content.id" class="align-top hover:bg-brand-50/40">
                                <td class="px-3 py-3 font-semibold w-32">{{ content.comp || '—' }}</td>
                                <td class="px-3 py-3 w-36">{{ content.jenis_postingan || '—' }}</td>
                                <td class="px-3 py-3 w-32">{{ content.kategori || '—' }}</td>
                                <td class="px-3 py-3 w-48"><a v-if="isUrl(content.referensi)" :href="content.referensi" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Buka referensi</a><span v-else class="line-clamp-4 whitespace-pre-line">{{ content.referensi || '—' }}</span></td>
                                <td class="px-3 py-3 w-52"><p class="line-clamp-5 whitespace-pre-line">{{ content.inti_pesan || '—' }}</p></td>
                                <td class="px-3 py-3 w-60"><p class="line-clamp-5 whitespace-pre-line">{{ content.hook_material || '—' }}</p></td>
                                <td class="px-3 py-3 w-72"><p class="line-clamp-6 whitespace-pre-line">{{ content.brief_original || '—' }}</p></td>
                                <td class="px-3 py-3 w-64"><p class="line-clamp-6 whitespace-pre-line">{{ content.opsi_brief || '—' }}</p></td>
                                <td class="px-3 py-3 w-72"><p class="line-clamp-6 whitespace-pre-line">{{ content.script_remake || '—' }}</p></td>
                                <td class="px-3 py-3 w-32">{{ content.editor || '—' }}</td>
                                <td class="px-3 py-3 w-28"><span :class="['inline-flex rounded-full px-2.5 py-1 font-semibold', progressClass(content.progress)]">{{ progressOptions[content.progress] || content.progress }}</span></td>
                                <td class="px-3 py-3 w-32">{{ fmtDate(content.tanggal_upload) }}</td>
                                <td v-for="field in ['link_hasil_editing', 'link_b_roll']" :key="field" class="px-3 py-3 w-44"><a v-if="isUrl(content[field])" :href="content[field]" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Buka link</a><span v-else>{{ content[field] || '—' }}</span></td>
                                <td class="px-3 py-3 w-64"><p class="line-clamp-6 whitespace-pre-line">{{ content.caption || '—' }}</p></td>
                                <td class="px-3 py-3 w-48"><a v-if="isUrl(content.link_ai_kata_kunci)" :href="content.link_ai_kata_kunci" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Buka link AI</a><span v-else class="whitespace-pre-line">{{ content.link_ai_kata_kunci || '—' }}</span></td>
                                <td v-if="canManage" class="px-3 py-3 sticky right-0 bg-white whitespace-nowrap">
                                    <button @click="openEdit(content)" class="text-brand-700 font-semibold mr-3">Edit</button>
                                    <button @click="destroyContent(content)" class="text-red-600 font-semibold">Hapus</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination Laravel; link mempertahankan parameter minggu. -->
            <div v-if="contents.last_page > 1" class="mt-5 flex flex-wrap gap-1">
                <Link v-for="link in contents.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                      :class="['px-3 py-2 rounded-lg text-sm border', link.active ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-slate-600 border-slate-200', !link.url && 'opacity-40 pointer-events-none']" />
            </div>
        </div>

        <!-- Modal besar dua kolom supaya 16 field tetap nyaman diisi. -->
        <ModalWrap v-if="open" width="max-w-6xl" align="items-start" @close="open = false">
            <form @submit.prevent="submit">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-slate-700">{{ editId ? 'Edit Content' : 'Tambah Content' }}</h2>
                    <button type="button" @click="open = false" class="text-slate-400 text-xl">×</button>
                </div>
                <div class="grid md:grid-cols-2 gap-4 max-h-[70vh] overflow-y-auto pr-2">
                    <label v-for="field in ['comp', 'jenis_postingan', 'kategori', 'editor']" :key="field" class="text-sm font-medium text-slate-600">
                        {{ { comp: 'Comp', jenis_postingan: 'Jenis Postingan', kategori: 'Kategori', editor: 'Editor' }[field] }}
                        <input v-model="form[field]" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-brand-400" />
                        <span v-if="form.errors[field]" class="text-xs text-red-600">{{ form.errors[field] }}</span>
                    </label>
                    <label class="text-sm font-medium text-slate-600">Progress<select v-model="form.progress" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-brand-400"><option v-for="(label, key) in progressOptions" :key="key" :value="key">{{ label }}</option></select></label>
                    <label class="text-sm font-medium text-slate-600">Tanggal Upload<input v-model="form.tanggal_upload" type="date" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-brand-400" /><span v-if="form.errors.tanggal_upload" class="text-xs text-red-600">{{ form.errors.tanggal_upload }}</span></label>
                    <label v-for="field in ['referensi', 'link_hasil_editing', 'link_b_roll', 'link_ai_kata_kunci']" :key="field" class="text-sm font-medium text-slate-600">
                        {{ { referensi: 'Referensi', link_hasil_editing: 'Link Hasil Editing', link_b_roll: 'Link B Roll', link_ai_kata_kunci: 'Link AI + Kata Kunci' }[field] }}
                        <textarea v-model="form[field]" rows="2" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-brand-400"></textarea>
                        <span v-if="form.errors[field]" class="text-xs text-red-600">{{ form.errors[field] }}</span>
                    </label>
                    <label v-for="field in ['inti_pesan', 'hook_material', 'brief_original', 'opsi_brief', 'script_remake', 'caption']" :key="field" class="text-sm font-medium text-slate-600 md:col-span-2">
                        {{ { inti_pesan: 'Inti Pesan', hook_material: 'Hook & Material', brief_original: 'Brief Original', opsi_brief: 'Opsi Brief/Fiks', script_remake: 'Script Remake', caption: 'Caption' }[field] }}
                        <textarea v-model="form[field]" rows="5" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-brand-400"></textarea>
                        <span v-if="form.errors[field]" class="text-xs text-red-600">{{ form.errors[field] }}</span>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="open = false" class="px-4 py-2 text-slate-600">Batal</button>
                    <button :disabled="form.processing" class="px-5 py-2 rounded-xl bg-brand-700 text-white font-semibold disabled:opacity-50">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

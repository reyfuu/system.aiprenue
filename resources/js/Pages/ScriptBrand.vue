<script setup>
// Galeri paket PDF satu brand. Agen tetap menyimpan 30 naskah sebagai baris
// terpisah, tetapi pengguna cukup melihat satu berkas per tanggal paket.
import { useForm, Link } from '@inertiajs/vue3';
import Layout from '../Layout.vue';

const props = defineProps({
    brand: Object,                              // { key, name }
    packs: { type: Array, default: () => [] },  // [{ date, label, count, name, pdf }]
    canManage: Boolean,                         // role kelola boleh upload PDF manual
    uploadUrl: String,                          // proyek tanpa Ziggy: URL dikirim controller
});

const form = useForm({ pdf: null, generated_for: new Date().toISOString().slice(0, 10) });
const upload = () => form.post(props.uploadUrl, { forceFormData: true, onSuccess: () => form.reset('pdf') });
</script>

<template>
    <Layout :title="'Script — ' + brand.name">
        <!-- Header brand + jalan pulang ke galeri semua brand -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center gap-3">
                <Link href="/script" title="Semua brand" class="text-brand-100 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ brand.name.toUpperCase() }}</h1>
                    <p class="text-brand-100 text-sm">{{ packs.length }} paket PDF</p>
                </div>
            </div>
        </header>

        <div class="px-6 py-6">
            <!-- Upload manual untuk paket PDF script brand ini. Satu tanggal = satu paket;
                 upload tanggal sama akan mengganti paket lama agar tidak dobel. -->
            <form v-if="canManage" @submit.prevent="upload" class="mb-6 bg-white rounded-2xl border border-brand-100 shadow-sm p-4 grid gap-3 md:grid-cols-[1fr_auto_auto] md:items-end">
                <label class="text-sm font-medium text-slate-600">
                    Upload PDF script
                    <input type="file" accept="application/pdf" @input="form.pdf = $event.target.files[0]"
                           class="mt-1 block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-700 file:font-semibold hover:file:bg-brand-100" />
                    <span v-if="form.errors.pdf" class="block mt-1 text-xs text-red-600">{{ form.errors.pdf }}</span>
                </label>
                <label class="text-sm font-medium text-slate-600">
                    Tanggal paket
                    <input v-model="form.generated_for" type="date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none" />
                    <span v-if="form.errors.generated_for" class="block mt-1 text-xs text-red-600">{{ form.errors.generated_for }}</span>
                </label>
                <button :disabled="form.processing || !form.pdf" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50">
                    {{ form.processing ? 'Mengupload…' : 'Upload PDF' }}
                </button>
            </form>

            <!-- Kosong: brand tetap terlihat di galeri walau agen belum mengirim paket. -->
            <p v-if="!packs.length" class="text-sm text-slate-400 py-12 text-center">
                Belum ada paket PDF untuk brand ini.
            </p>

            <!-- Satu kartu = satu PDF utuh berisi seluruh naskah pada tanggal itu. -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <a v-for="pack in packs" :key="pack.date" :href="pack.pdf" target="_blank" rel="noopener"
                   class="group block bg-white rounded-2xl border border-brand-100 shadow-sm hover:border-brand-300 hover:shadow-md transition overflow-hidden">
                    <!-- Pratinjau visual sederhana seperti kartu berkas di Drive; isi PDF
                         tidak di-embed agar browser tidak mengunduh dokumen dua kali. -->
                    <div class="h-44 bg-slate-100 p-5 flex items-center justify-center">
                        <div class="w-full h-full bg-white border border-slate-200 rounded-lg shadow-sm p-3 space-y-2 overflow-hidden">
                            <div class="h-2 w-2/3 bg-brand-200 rounded"></div>
                            <div v-for="n in 7" :key="n" class="space-y-1">
                                <div class="h-1.5 bg-slate-200 rounded" :class="n % 2 ? 'w-full' : 'w-5/6'"></div>
                                <div class="h-1 bg-slate-100 rounded w-full"></div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-red-50 text-red-600 flex items-center justify-center text-[10px] font-black flex-shrink-0">PDF</div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-700 truncate group-hover:text-brand-700">{{ pack.name }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ pack.label }} · {{ pack.count }} naskah</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </Layout>
</template>

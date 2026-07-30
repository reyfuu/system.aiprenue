<script setup>
// Galeri paket PDF satu brand. Agen tetap menyimpan 30 naskah sebagai baris
// terpisah, tetapi pengguna cukup melihat satu berkas per tanggal paket.
import { Link } from '@inertiajs/vue3';
import Layout from '../Layout.vue';

defineProps({
    brand: Object,                              // { key, name }
    packs: { type: Array, default: () => [] },  // [{ date, label, count, name, pdf }]
});
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

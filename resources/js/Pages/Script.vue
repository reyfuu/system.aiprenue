<script setup>
// Halaman Script — galeri brand. Isinya datang dari agen Daily Script Rave
// (repo privat, jalan di GitHub Actions) lewat POST /api/scripts.
//
// NB: versi lama halaman ini template mati — daftar folder di-hardcode di Vue,
// tombol "Folder Baru" & menu kebab (Buka/Ubah nama/Hapus) tak berfungsi sama
// sekali. Semuanya dibuang: brand kini datang dari DB, dan naskah dibuat agen,
// bukan lewat tombol di sini.
import { Link } from '@inertiajs/vue3';   // navigasi ke halaman brand
import Layout from '../Layout.vue';       // kerangka (sidebar + toast)

// Props dari ScriptController@index
defineProps({
    brands: { type: Array, default: () => [] },   // [{ key, name, count, latest }]
});
</script>

<template>
    <Layout title="Script">
        <!-- Header -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">SCRIPT</h1>
                <p class="text-brand-100 text-sm">Naskah Reels/TikTok per brand — dibuat otomatis tiap Jumat, Sabtu, Minggu</p>
            </div>
        </header>

        <div class="px-6 py-6">
            <!-- Grid brand: satu kartu per brand, angkanya nyata dari DB -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link v-for="b in brands" :key="b.key" :href="`/script/${b.key}`"
                      class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center gap-3">
                        <!-- ikon folder -->
                        <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" /></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-slate-700 truncate">{{ b.name }}</p>
                            <p class="text-xs text-slate-400">{{ b.count }} naskah</p>
                        </div>
                    </div>
                    <!-- Paket terbaru = jawaban cepat "agennya masih jalan tidak?" -->
                    <p class="text-xs text-slate-400 mt-3">
                        <template v-if="b.latest">Paket terbaru: <span class="font-semibold text-slate-600">{{ b.latest }}</span></template>
                        <template v-else>Belum ada naskah.</template>
                    </p>
                </Link>
            </div>
        </div>
    </Layout>
</template>

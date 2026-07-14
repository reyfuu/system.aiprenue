<script setup>
// Halaman Script — grid folder naskah (template). Port dari script/index.blade.php.
import { ref } from 'vue';           // ref untuk state menu kebab
import Layout from '../Layout.vue';  // Layout sudah render sidebar + flash toast

// Data folder statis (nanti diisi Hermes agent ke public/scripts/*)
const FOLDERS = [
    { name: 'Script FK', path: 'scripts/fk', count: null },
    { name: 'Script Rave Tailor', path: 'scripts/rave-tailor', count: null },
    { name: 'Script Raveloux', path: 'scripts/raveloux', count: null },
];

// Index folder yang menu kebab-nya terbuka (null = tidak ada yang terbuka)
const menu = ref(null);

// Toggle menu kebab: buka bila tertutup, tutup bila index sama
const toggleMenu = (i) => {
    menu.value = menu.value === i ? null : i;
};
</script>

<template>
    <Layout title="Script">
        <!-- Header + tombol folder baru (template) -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">SCRIPT</h1>
                    <p class="text-brand-100 text-sm">Kumpulan folder naskah</p>
                </div>
                <button class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Folder Baru
                </button>
            </div>
        </header>

        <div class="px-6 py-6">
            <!-- Bar sort (template) -->
            <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-4">
                <span class="font-semibold text-slate-600">Nama</span>
                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
            </div>

            <!-- Grid folder -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                <!-- Loop folder statis -->
                <div v-for="(f, i) in FOLDERS" :key="i" class="group relative bg-white hover:bg-brand-50 border border-brand-100 hover:border-brand-200 rounded-xl px-4 py-3.5 flex items-center gap-3 cursor-pointer transition shadow-sm">
                    <!-- Ikon folder -->
                    <svg class="w-6 h-6 text-brand-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" /></svg>
                    <!-- Nama folder -->
                    <span class="flex-1 font-medium text-slate-700 truncate">{{ f.name }}</span>
                    <!-- Jumlah file (bila ada) -->
                    <span v-if="f.count !== null" class="text-xs text-slate-400">{{ f.count }}</span>
                    <!-- Tombol kebab: stop propagation lalu toggle menu -->
                    <button
                        @click.stop="toggleMenu(i)"
                        class="p-1 rounded-md text-slate-400 hover:bg-brand-100 hover:text-slate-600 transition"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4z" /></svg>
                    </button>
                    <!-- Menu kebab (template) — tampil bila index cocok -->
                    <div v-if="menu === i" class="absolute right-3 top-12 z-20 w-40 bg-white border border-brand-100 rounded-xl shadow-lg py-1 text-sm" @click.stop>
                        <button class="w-full text-left px-4 py-2 hover:bg-brand-50 text-slate-600">Buka</button>
                        <button class="w-full text-left px-4 py-2 hover:bg-brand-50 text-slate-600">Ubah nama</button>
                        <button class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600">Hapus</button>
                    </div>
                </div>
            </div>

            <p class="text-xs text-slate-400 mt-6">Template tampilan — data folder script akan dikirim otomatis oleh Hermes agent.</p>
        </div>
    </Layout>
</template>

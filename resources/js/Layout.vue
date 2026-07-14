<script setup>
// Layout utama: sidebar + area konten (slot) + toast flash. Dipakai semua halaman ber-sidebar.
import { ref, watch } from 'vue';                 // ref state, watch untuk pantau flash
import { Head, usePage } from '@inertiajs/vue3';  // Head judul tab, usePage shared props
import Sidebar from './Sidebar.vue';              // sidebar navigasi

// Judul halaman (opsional) → dipakai di <Head>
defineProps({ title: { type: String, default: '' } });

const page = usePage();          // akses flash.status
const toast = ref(null);         // pesan toast aktif

// Pantau flash.status; tiap ada pesan baru → tampilkan toast, hilang 3 detik
watch(
    () => page.props.flash?.status,
    (status) => {
        if (status) {
            toast.value = status;                          // set pesan
            setTimeout(() => (toast.value = null), 3000);  // auto-hide
        }
    },
    { immediate: true }, // jalankan sekali saat mount (flash dari redirect awal)
);
</script>

<template>
    <!-- Judul tab per halaman -->
    <Head v-if="title" :title="`${title} — System AI Preneur`" />

    <!-- Sidebar tetap di kiri -->
    <Sidebar />

    <!-- Konten digeser 56 (lebar sidebar) di layar md+ -->
    <div class="md:ml-56">
        <slot />
    </div>

    <!-- Toast flash message di kanan bawah -->
    <div v-if="toast" class="fixed bottom-5 right-5 z-50 bg-brand-700 text-white text-sm px-4 py-3 rounded-xl shadow-lg">
        {{ toast }}
    </div>
</template>

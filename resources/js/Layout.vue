<script setup>
// Layout utama: sidebar + area konten (slot) + toast flash. Dipakai semua halaman ber-sidebar.
import { ref, watch } from 'vue';                 // ref state, watch untuk pantau flash
import { Head, usePage, router } from '@inertiajs/vue3'; // Head judul tab, usePage shared props, router aksi
import Sidebar from './Sidebar.vue';              // sidebar navigasi

// Kembali ke akun owner asli saat sedang "masuk sebagai" peran lain.
const stopImpersonate = () => router.post('/impersonate/stop');

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

    <!-- Bilah "sedang menyamar": muncul saat owner masuk sebagai peran lain.
         Amber mencolok supaya tak lupa ini bukan akun sendiri. -->
    <div v-if="page.props.impersonating"
         class="fixed top-0 right-0 left-0 md:left-56 z-40 bg-amber-500 text-amber-950 text-sm px-4 py-2 flex items-center justify-between shadow">
        <span>Kamu sedang masuk sebagai <b>{{ page.props.auth?.user?.name }}</b> ({{ page.props.auth?.user?.role }}) — hanya untuk melihat aksesnya.</span>
        <button type="button" @click="stopImpersonate"
                class="bg-amber-950 text-amber-50 font-semibold px-3 py-1 rounded-lg hover:bg-amber-900 whitespace-nowrap">
            Kembali ke akun saya
        </button>
    </div>

    <!-- Konten digeser 56 (lebar sidebar) di layar md+. Beri jarak atas saat bilah menyamar aktif. -->
    <div class="md:ml-56" :class="{ 'pt-10': page.props.impersonating }">
        <slot />
    </div>

    <!-- Toast flash message di kanan bawah -->
    <div v-if="toast" class="fixed bottom-5 right-5 z-50 bg-brand-700 text-white text-sm px-4 py-3 rounded-xl shadow-lg">
        {{ toast }}
    </div>
</template>

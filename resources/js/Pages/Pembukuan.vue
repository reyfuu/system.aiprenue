<script setup>
// Halaman Inertia Pembukuan: bungkus komponen chart dengan layout + header brand.
import Layout from '../Layout.vue'; // Layout bersama (sidebar + toast flash)
import Pembukuan from '../scripts/components/Pembukuan.vue'; // komponen chart (butuh prop `data`)

// payload dari controller: { summary, monthly, incomeByCat, expenseByCat, inventory, reportUrl }
defineProps({ payload: Object });
</script>

<template>
    <Layout title="Pembukuan">
        <!-- Header gradient brand seperti blade lama -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center justify-between">
                <!-- Blok teks judul -->
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">PEMBUKUAN</h1>
                    <p class="text-brand-100 text-sm">Pemasukan, pengeluaran &amp; inventaris</p>
                </div>
                <!-- Tombol export PDF ke url laporan (hanya bila tersedia) -->
                <a v-if="payload.reportUrl" :href="payload.reportUrl" target="_blank" rel="noreferrer"
                   class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                    <!-- Ikon dokumen -->
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V18a2 2 0 01-2 2z" />
                    </svg>
                    Export PDF
                </a>
            </div>
        </header>

        <!-- Area konten: komponen menerima seluruh payload lewat prop `data` -->
        <div class="px-6 py-6">
            <Pembukuan :data="payload" />
        </div>
    </Layout>
</template>

<script setup>
// Halaman Dashboard — ringkasan Pipeline, Kanban, Pembukuan. Port dari dashboard.blade.php.
import { Link } from '@inertiajs/vue3'; // Link untuk navigasi Inertia tanpa reload
import Layout from '../Layout.vue';      // Layout sudah render sidebar + flash toast

// Props dari controller. Di template diakses langsung ({{ total }}), di script pakai props.x.
const props = defineProps({
    rate: [Number, String],         // kurs USD→IDR
    total: [Number, String],        // total entri
    totalIdr: [Number, String],     // omzet IDR
    totalUsd: [Number, String],     // omzet USD
    grandIdr: [Number, String],     // grand total omzet
    lunas: [Number, String],        // jumlah lunas
    outstanding: [Number, String],  // jumlah outstanding
    done: [Number, String],         // jumlah done
    perCategory: { type: Object, default: () => ({}) }, // hitungan per kategori
    perProgress: { type: Object, default: () => ({}) }, // hitungan per progress
    categories: { type: Object, default: () => ({}) },  // peta key→label kategori
    progresses: { type: Object, default: () => ({}) },  // peta key→label progress
});

// Helper format Rupiah: 1234567 → "Rp 1.234.567"
const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

// Format USD: 1234.5 → "1,234.50"
const usd = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Warna titik/bar per progress (samakan dengan blade lama)
const progressDot = {
    script: 'bg-purple-500',
    editing: 'bg-sky-500',
    progress: 'bg-brand-600',
    pending: 'bg-amber-500',
    done: 'bg-emerald-500',
};

// Hitung persentase bar progress: jumlah kartu / total × 100
const pctOf = (pk) => {
    const c = props.perProgress[pk] ?? 0;
    return props.total ? Math.round((c / props.total) * 100) : 0;
};
</script>

<template>
    <Layout title="Dashboard">
        <!-- Header gradient -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">DASHBOARD</h1>
                <p class="text-brand-100 text-sm">Ringkasan sistem AI Preneur — kurs 1 USD = {{ rp(rate) }}</p>
            </div>
        </header>

        <div class="px-6 py-6 space-y-6">
            <!-- Kartu statistik cepat -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <!-- Total entri -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Total Entri</p>
                    <p class="text-2xl font-bold text-brand-700 mt-1">{{ total }}</p>
                </div>
                <!-- Grand omzet -->
                <div class="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                    <p class="text-xs text-brand-100 font-medium">Grand Omzet (IDR)</p>
                    <p class="text-2xl font-bold mt-1">{{ rp(grandIdr) }}</p>
                </div>
                <!-- Lunas -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Lunas</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ lunas }}<span class="text-sm text-slate-400 font-medium"> / {{ total }}</span></p>
                </div>
                <!-- Outstanding -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Outstanding</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ outstanding }}</p>
                </div>
            </div>

            <!-- Tiga kartu ringkasan modul -->
            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Pipeline: hitungan per kategori -->
                <Link href="/pipelines" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Pipeline</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ total }} <span class="text-sm text-slate-400 font-medium">entri</span></p>
                    <div class="mt-4 space-y-2">
                        <!-- Loop kategori board: iterasi objek (label cv, key ck) -->
                        <div v-for="(cv, ck) in categories" :key="ck" class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">{{ cv }}</span>
                            <span class="font-semibold text-slate-700">{{ perCategory[ck] ?? 0 }}</span>
                        </div>
                    </div>
                </Link>

                <!-- Kanban: bar per progress -->
                <Link href="/pipelines/kanban" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Kanban</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ done }} <span class="text-sm text-slate-400 font-medium">/ {{ total }} done</span></p>
                    <div class="mt-4 space-y-2.5">
                        <!-- Loop progress standar (label pv, key pk) -->
                        <div v-for="(pv, pk) in progresses" :key="pk">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="flex items-center gap-1.5 text-slate-500">
                                    <!-- Titik warna sesuai progressDot -->
                                    <span :class="'w-2 h-2 rounded-full ' + (progressDot[pk] || 'bg-slate-400')"></span>{{ pv }}
                                </span>
                                <span class="font-semibold text-slate-700">{{ perProgress[pk] ?? 0 }}</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-brand-50 overflow-hidden">
                                <!-- Bar isi: lebar = persentase kartu progress ini -->
                                <div :class="'h-full ' + (progressDot[pk] || 'bg-slate-400')" :style="{ width: pctOf(pk) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </Link>

                <!-- Pembukuan: omzet -->
                <Link href="/pembukuan" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Pembukuan</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ rp(grandIdr) }}</p>
                    <p class="text-xs text-slate-400 mt-1">Grand total omzet</p>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Omzet IDR</span>
                            <span class="font-semibold text-slate-700">{{ rp(totalIdr) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Omzet USD</span>
                            <span class="font-semibold text-slate-700">$ {{ usd(totalUsd) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Kurs USD→IDR</span>
                            <span class="font-semibold text-slate-700">{{ rp(rate) }}</span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </Layout>
</template>

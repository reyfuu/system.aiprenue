<script setup>
// Komponen utama Pembukuan: menampilkan ringkasan, chart bar bulanan,
// dua doughnut komposisi kategori, tabel laba/rugi bulanan, dan tabel inventaris.
import { computed } from 'vue';
import '../lib/charts'; // registrasi elemen Chart.js sekali (framework-agnostic, tetap dibutuhkan vue-chartjs)
import { Bar, Doughnut } from 'vue-chartjs'; // komponen chart siap pakai dari vue-chartjs
import { rp, BRAND } from '../lib/format'; // formatter rupiah + palet warna brand
import StatCard from './StatCard.vue';
import RecapTable from './RecapTable.vue';

// payload dari controller di-oper lewat prop `data`
const props = defineProps({ data: Object });

// Pintasan ke tiap seksi payload agar template lebih ringkas
const summary = computed(() => props.data.summary);
const monthly = computed(() => props.data.monthly);
const incomeByCat = computed(() => props.data.incomeByCat);
const expenseByCat = computed(() => props.data.expenseByCat);
const inventory = computed(() => props.data.inventory);

const hasMonthly = computed(() => monthly.value.length > 0); // apakah ada data bulanan untuk bar chart
const labaPositif = computed(() => summary.value.laba >= 0); // penentu label kartu Laba vs Rugi

// --- Data bar chart: pemasukan (hijau) vs pengeluaran (merah) per bulan ---
const barData = computed(() => ({
    labels: monthly.value.map((m) => m.label),
    datasets: [
        { label: 'Pemasukan', data: monthly.value.map((m) => m.pemasukan), backgroundColor: '#059669', borderRadius: 6 },
        { label: 'Pengeluaran', data: monthly.value.map((m) => m.pengeluaran), backgroundColor: '#e11d48', borderRadius: 6 },
    ],
}));

// Opsi bar chart: legend di bawah, tooltip & sumbu-Y diformat rupiah
const barOpts = {
    responsive: true,
    maintainAspectRatio: false, // wajib false agar chart mengikuti tinggi container (h-64)
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
        tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${rp(c.parsed.y)}` } },
    },
    scales: {
        y: { ticks: { callback: (v) => 'Rp ' + Number(v).toLocaleString('id-ID') }, grid: { color: '#eef2ff' } },
        x: { grid: { display: false } },
    },
};

// Builder data doughnut dari daftar baris {label, value}, memakai palet BRAND.bars
const doughnut = (rows) => ({
    labels: rows.map((r) => r.label),
    datasets: [{ data: rows.map((r) => r.value), backgroundColor: BRAND.bars, borderWidth: 0 }],
});

// Data doughnut untuk pemasukan & pengeluaran per kategori (computed agar reaktif)
const incomeDoughnut = computed(() => doughnut(incomeByCat.value));
const expenseDoughnut = computed(() => doughnut(expenseByCat.value));

// Opsi doughnut: legend di bawah, tooltip rupiah, lubang tengah 58%
const doughnutOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
        tooltip: { callbacks: { label: (c) => `${c.label}: ${rp(c.parsed)}` } },
    },
    cutout: '58%',
};
</script>

<template>
    <div class="space-y-6">
        <!-- Ringkasan: 4 kartu statistik utama -->
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
            <StatCard label="Total Pemasukan" :value="rp(summary.totalIn)" />
            <StatCard label="Total Pengeluaran" :value="rp(summary.totalOut)" />
            <StatCard :label="labaPositif ? 'Laba' : 'Rugi'" :value="rp(summary.laba)" accent hint="Pemasukan − Pengeluaran" />
            <StatCard :label="`Nilai Inventaris (${summary.invMonthLabel})`" :value="rp(summary.invTotal)" />
        </div>

        <!-- Bar chart: pemasukan vs pengeluaran per bulan -->
        <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
            <h2 class="text-sm font-bold text-slate-700 mb-4">Pemasukan vs Pengeluaran per Bulan</h2>
            <div class="h-64">
                <!-- Ada data bulanan → render bar chart; jika tidak → placeholder -->
                <Bar v-if="hasMonthly" :data="barData" :options="barOpts" />
                <p v-else class="text-center text-slate-400 py-20 text-sm">Belum ada data transaksi.</p>
            </div>
        </div>

        <!-- Komposisi kategori: dua kartu doughnut + tabel rekap -->
        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Pemasukan per kategori -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <h2 class="text-sm font-bold text-slate-700 mb-4">Pemasukan per Kategori</h2>
                <div class="h-56">
                    <Doughnut v-if="incomeByCat.length" :data="incomeDoughnut" :options="doughnutOpts" />
                    <p v-else class="text-center text-slate-400 py-16 text-sm">Belum ada data.</p>
                </div>
                <RecapTable head="Kategori" :rows="incomeByCat" />
            </div>
            <!-- Pengeluaran per kategori -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <h2 class="text-sm font-bold text-slate-700 mb-4">Pengeluaran per Kategori</h2>
                <div class="h-56">
                    <Doughnut v-if="expenseByCat.length" :data="expenseDoughnut" :options="doughnutOpts" />
                    <p v-else class="text-center text-slate-400 py-16 text-sm">Belum ada data.</p>
                </div>
                <RecapTable head="Kategori" :rows="expenseByCat" />
            </div>
        </div>

        <!-- Rekap bulanan + inventaris -->
        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Tabel laba/rugi per bulan -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <h2 class="text-sm font-bold text-slate-700 mb-3">Laba/Rugi per Bulan</h2>
                <div class="overflow-hidden rounded-xl border border-brand-100">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                                <th class="px-3 py-2.5 text-left">Bulan</th>
                                <th class="px-3 py-2.5 text-right">Masuk</th>
                                <th class="px-3 py-2.5 text-right">Keluar</th>
                                <th class="px-3 py-2.5 text-right">Laba</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-50">
                            <!-- Baris tiap bulan; warna kolom Laba merah bila rugi -->
                            <tr v-for="(m, i) in monthly" :key="i" class="hover:bg-brand-50/60">
                                <td class="px-3 py-2.5 text-slate-600">{{ m.label }}</td>
                                <td class="px-3 py-2.5 text-right text-emerald-600">{{ rp(m.pemasukan) }}</td>
                                <td class="px-3 py-2.5 text-right text-red-600">{{ rp(m.pengeluaran) }}</td>
                                <td :class="'px-3 py-2.5 text-right font-semibold ' + (m.laba >= 0 ? 'text-slate-700' : 'text-red-600')">{{ rp(m.laba) }}</td>
                            </tr>
                            <!-- Kosong: placeholder lintas 4 kolom -->
                            <tr v-if="!monthly.length">
                                <td colspan="4" class="px-3 py-6 text-center text-slate-400">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel inventaris barang -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <h2 class="text-sm font-bold text-slate-700 mb-3">Inventaris Barang <span class="font-normal text-slate-400">({{ summary.invMonthLabel }})</span></h2>
                <div class="overflow-hidden rounded-xl border border-brand-100">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                                <th class="px-3 py-2.5 text-left">Barang</th>
                                <th class="px-3 py-2.5 text-right">Qty</th>
                                <th class="px-3 py-2.5 text-right">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-50">
                            <!-- Baris tiap item inventaris -->
                            <tr v-for="(it, i) in inventory" :key="i" class="hover:bg-brand-50/60">
                                <td class="px-3 py-2.5 text-slate-600">{{ it.name }}</td>
                                <td class="px-3 py-2.5 text-right">{{ it.qty }}</td>
                                <td class="px-3 py-2.5 text-right font-medium">{{ rp(it.total) }}</td>
                            </tr>
                            <!-- Kosong: placeholder lintas 3 kolom -->
                            <tr v-if="!inventory.length">
                                <td colspan="3" class="px-3 py-6 text-center text-slate-400">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer: waktu laporan dibuat -->
        <p class="text-xs text-slate-400">Dibuat {{ summary.generated }}</p>
    </div>
</template>

<script setup>
// Halaman Dashboard — ringkasan atas + kartu per modul (Pipeline, Kanban, Order, Mindmap, Script, Pembukuan).
import { computed } from 'vue';               // computed untuk turunan reaktif
import { Link, usePage } from '@inertiajs/vue3'; // Link navigasi Inertia, usePage untuk shared props
import Layout from '../Layout.vue';           // Layout sudah render sidebar + flash toast

// Props dari DashboardController — satu objek per modul
const props = defineProps({
    rate: [Number, String],                             // kurs USD→IDR
    summary: { type: Object, default: () => ({}) },     // ringkasan atas (grandIdr, total, lunas, outstanding)
    pipeline: { type: Object, default: () => ({}) },    // { total, grandIdr, perCategory, categories }
    kanban: { type: Object, default: () => ({}) },      // { total, done, boards, perProgress, progresses }
    order: { type: Object, default: () => ({}) },       // { total, urgent, dp, nilai, perTipe, tipeOrder }
    mindmap: { type: Object, default: () => ({}) },     // { total, latest }
    script: { type: Object, default: () => ({}) },      // { folders, files }
    pembukuan: { type: Object, default: () => ({}) },   // { pemasukan, pengeluaran, laba, transaksi, invTotal }
});

// Peta menu yang boleh dilihat user → kartu modul ikut digating seperti sidebar
const page = usePage();
const menus = computed(() => page.props.auth?.user?.menus ?? {});

// Helper format Rupiah: 1234567 → "Rp 1.234.567"
const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

// Warna titik/bar per progress kanban
const progressDot = {
    script: 'bg-purple-500',
    editing: 'bg-sky-500',
    progress: 'bg-brand-600',
    pending: 'bg-amber-500',
    done: 'bg-emerald-500',
};

// Warna titik per tipe order (samakan dengan Order::TIPE_COLORS)
const tipeDot = {
    coaching: 'bg-brand-600',
    endorse: 'bg-emerald-500',
    speaker: 'bg-amber-500',
    agency: 'bg-rose-500',
};

// Persentase bar progress kanban: jumlah task progress ini / total task × 100
const pctOf = (pk) => {
    const c = props.kanban.perProgress?.[pk] ?? 0;
    return props.kanban.total ? Math.round((c / props.kanban.total) * 100) : 0;
};

// Laba positif → hijau, rugi → merah
const labaPositif = computed(() => (props.pembukuan.laba ?? 0) >= 0);
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
            <!-- ===== Ringkasan cepat (angka bisnis pipeline) ===== -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <!-- Grand omzet (sengaja paling kiri) -->
                <div class="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                    <p class="text-xs text-brand-100 font-medium">Grand Omzet (IDR)</p>
                    <p class="text-2xl font-bold mt-1">{{ rp(summary.grandIdr) }}</p>
                </div>
                <!-- Total entri -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Total Entri</p>
                    <p class="text-2xl font-bold text-brand-700 mt-1">{{ summary.total }}</p>
                </div>
                <!-- Lunas -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Lunas</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.lunas }}<span class="text-sm text-slate-400 font-medium"> / {{ summary.total }}</span></p>
                </div>
                <!-- Outstanding -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Outstanding</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ summary.outstanding }}</p>
                </div>
            </div>

            <!-- ===== Kartu ringkasan per modul ===== -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- ---- Pipeline: entri per board + omzet ---- -->
                <Link v-if="menus.pipeline" href="/pipelines" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Pipeline</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ pipeline.total }} <span class="text-sm text-slate-400 font-medium">entri</span></p>
                    <p class="text-xs text-slate-400 mt-1">Omzet {{ rp(pipeline.grandIdr) }}</p>
                    <div class="mt-4 space-y-2">
                        <!-- Loop board pipeline (label cv, key ck) -->
                        <div v-for="(cv, ck) in pipeline.categories" :key="ck" class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">{{ cv }}</span>
                            <span class="font-semibold text-slate-700">{{ pipeline.perCategory?.[ck] ?? 0 }}</span>
                        </div>
                    </div>
                </Link>

                <!-- ---- Kanban: task per progress ---- -->
                <Link v-if="menus.kanban" href="/pipelines/kanban" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Kanban</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ kanban.done }} <span class="text-sm text-slate-400 font-medium">/ {{ kanban.total }} done</span></p>
                    <p class="text-xs text-slate-400 mt-1">{{ kanban.boards }} board</p>
                    <div class="mt-4 space-y-2.5">
                        <!-- Loop progress standar (label pv, key pk) -->
                        <div v-for="(pv, pk) in kanban.progresses" :key="pk">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="flex items-center gap-1.5 text-slate-500">
                                    <span :class="'w-2 h-2 rounded-full ' + (progressDot[pk] || 'bg-slate-400')"></span>{{ pv }}
                                </span>
                                <span class="font-semibold text-slate-700">{{ kanban.perProgress?.[pk] ?? 0 }}</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-brand-50 overflow-hidden">
                                <!-- Bar isi: lebar = persentase task progress ini -->
                                <div :class="'h-full ' + (progressDot[pk] || 'bg-slate-400')" :style="{ width: pctOf(pk) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </Link>

                <!-- ---- Order: nilai pembayaran + order per tipe ---- -->
                <Link v-if="menus.order" href="/orders" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Order</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ order.total }} <span class="text-sm text-slate-400 font-medium">order</span></p>
                    <p class="text-xs text-slate-400 mt-1">Nilai {{ rp(order.nilai) }}</p>
                    <div class="mt-4 space-y-2">
                        <!-- Loop tipe order (label tv, key tk) -->
                        <div v-for="(tv, tk) in order.tipeOrder" :key="tk" class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-1.5 text-slate-500">
                                <span :class="'w-2 h-2 rounded-full ' + (tipeDot[tk] || 'bg-slate-400')"></span>{{ tv }}
                            </span>
                            <span class="font-semibold text-slate-700">{{ order.perTipe?.[tk] ?? 0 }}</span>
                        </div>
                        <!-- Pemisah + status yang perlu perhatian -->
                        <div class="flex items-center justify-between text-sm pt-2 border-t border-brand-50">
                            <span class="text-slate-500">Masih DP</span>
                            <span class="font-semibold text-amber-600">{{ order.dp }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Urgent</span>
                            <span class="font-semibold text-red-600">{{ order.urgent }}</span>
                        </div>
                    </div>
                </Link>

                <!-- ---- Mindmap ---- -->
                <Link v-if="menus.mindmap" href="/mindmaps" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Mindmap</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ mindmap.total }} <span class="text-sm text-slate-400 font-medium">mindmap</span></p>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Terakhir diubah</span>
                            <!-- truncate: judul mindmap bisa panjang -->
                            <span class="font-semibold text-slate-700 truncate max-w-[55%]">{{ mindmap.latest || '—' }}</span>
                        </div>
                    </div>
                </Link>

                <!-- ---- Script ---- -->
                <Link v-if="menus.script" href="/script" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Script</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p class="text-3xl font-bold text-brand-700">{{ script.files }} <span class="text-sm text-slate-400 font-medium">naskah</span></p>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Folder</span>
                            <span class="font-semibold text-slate-700">{{ script.folders }}</span>
                        </div>
                        <!-- Folder masih kosong = naskah belum dikirim Hermes agent -->
                        <p v-if="script.files === 0" class="text-xs text-slate-400 pt-1">Folder masih kosong — naskah belum diisi.</p>
                    </div>
                </Link>

                <!-- ---- Pembukuan: dari transaksi, bukan omzet pipeline ---- -->
                <Link v-if="menus.pembukuan" href="/pembukuan" class="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-slate-700">Pembukuan</h2>
                        <span class="text-xs text-brand-600 font-semibold">Lihat →</span>
                    </div>
                    <p :class="'text-3xl font-bold ' + (labaPositif ? 'text-brand-700' : 'text-red-600')">{{ rp(pembukuan.laba) }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ labaPositif ? 'Laba' : 'Rugi' }} · {{ pembukuan.transaksi }} transaksi</p>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Pemasukan</span>
                            <span class="font-semibold text-emerald-600">{{ rp(pembukuan.pemasukan) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Pengeluaran</span>
                            <span class="font-semibold text-red-600">{{ rp(pembukuan.pengeluaran) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Nilai Inventaris</span>
                            <span class="font-semibold text-slate-700">{{ rp(pembukuan.invTotal) }}</span>
                        </div>
                        <!-- Bedakan "belum ada data" dari "laba nol" -->
                        <p v-if="pembukuan.transaksi === 0" class="text-xs text-slate-400 pt-1">Belum ada transaksi tercatat.</p>
                    </div>
                </Link>
            </div>
        </div>
    </Layout>
</template>

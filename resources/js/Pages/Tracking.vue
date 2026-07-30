<script setup>
// Dashboard progress read-only untuk Owner/Manager. Semua angka berasal dari
// kartu Kanban sehingga tidak ada status kedua yang harus diisi manual.
import { Link } from '@inertiajs/vue3';
import Layout from '../Layout.vue';

defineProps({
    tracking: { type: Array, default: () => [] },
    summary: Object,
});

const health = {
    green: { label: 'Sesuai jalur', dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    yellow: { label: 'Perlu perhatian', dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    red: { label: 'Terlambat', dot: 'bg-red-500', badge: 'bg-red-50 text-red-700 border-red-200' },
};
</script>

<template>
    <Layout title="Tracking">
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">PROGRESS TRACKING</h1>
                <p class="text-brand-100 text-sm">Ringkasan otomatis dari seluruh board Kanban</p>
            </div>
        </header>

        <div class="p-6">
            <!-- Ringkasan lintas project untuk jawaban cepat di level owner. -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Project</p><p class="text-2xl font-bold text-brand-700 mt-1">{{ summary.boards }}</p></div>
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Total Kartu</p><p class="text-2xl font-bold text-slate-700 mt-1">{{ summary.cards }}</p></div>
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Selesai</p><p class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.done }}</p></div>
                <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Overdue</p><p class="text-2xl font-bold text-red-600 mt-1">{{ summary.overdue }}</p></div>
            </div>

            <p v-if="!tracking.length" class="text-center text-sm text-slate-400 py-16">Belum ada board Kanban untuk dilacak.</p>

            <!-- Satu kartu tracking per board; klik detail tetap menuju sumber Kanban. -->
            <div v-else class="grid xl:grid-cols-2 gap-4">
                <article v-for="board in tracking" :key="board.key" class="bg-white border border-brand-100 rounded-2xl shadow-sm p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-bold text-lg text-slate-700">{{ board.name }}</h2>
                            <p class="text-xs text-slate-400 mt-0.5">Aktivitas terakhir: {{ board.last_activity || 'belum ada' }}</p>
                        </div>
                        <span :class="['inline-flex items-center gap-1.5 border rounded-full px-2.5 py-1 text-xs font-semibold', health[board.health].badge]">
                            <span :class="['w-2 h-2 rounded-full', health[board.health].dot]"></span>{{ health[board.health].label }}
                        </span>
                    </div>

                    <div class="mt-5 flex items-end justify-between">
                        <div><span class="text-3xl font-bold text-brand-700">{{ board.percent }}%</span><span class="text-xs text-slate-400 ml-2">{{ board.done }}/{{ board.total }} selesai</span></div>
                        <Link :href="board.url" class="text-xs font-semibold text-brand-700 hover:underline">Buka Kanban →</Link>
                    </div>
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden mt-2">
                        <div class="h-full bg-brand-600 rounded-full transition-all" :style="{ width: board.percent + '%' }"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                        <div class="rounded-xl bg-blue-50 p-2"><p class="text-lg font-bold text-blue-700">{{ board.active }}</p><p class="text-[10px] text-blue-600">Aktif</p></div>
                        <div class="rounded-xl bg-red-50 p-2"><p class="text-lg font-bold text-red-700">{{ board.overdue }}</p><p class="text-[10px] text-red-600">Overdue</p></div>
                        <div class="rounded-xl bg-amber-50 p-2"><p class="text-lg font-bold text-amber-700">{{ board.urgent }}</p><p class="text-[10px] text-amber-600">Urgent</p></div>
                    </div>

                    <!-- Distribusi menunjukkan "sekarang sampai mana" per tahap. -->
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <p class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold mb-2">Distribusi tahap</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="column in board.columns" :key="column.name" class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-slate-50 text-slate-600 border border-slate-200">
                                <span :class="['w-2 h-2 rounded-full', column.color]"></span>{{ column.name }} <b>{{ column.count }}</b>
                            </span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </Layout>
</template>

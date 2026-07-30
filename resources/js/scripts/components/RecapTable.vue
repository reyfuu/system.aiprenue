<script setup>
// Tabel rekap sederhana: kolom kiri (kategori) + kolom kanan (omzet IDR).
import { rp } from '../lib/format';

defineProps({
    head: String, // judul kolom pertama (mis. "Kategori")
    rows: { type: Array, default: () => [] }, // baris data: [{ label, value }]
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-brand-100 mt-4">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                    <th class="px-4 py-2.5 text-left">{{ head }}</th>
                    <th class="px-4 py-2.5 text-right">Omzet (IDR)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                <!-- Ada data: render tiap baris -->
                <tr v-for="(r, i) in rows" :key="i" class="hover:bg-brand-50/60">
                    <td class="px-4 py-2.5 text-slate-600">{{ r.label }}</td>
                    <td class="px-4 py-2.5 text-right font-medium">{{ rp(r.value) }}</td>
                </tr>
                <!-- Kosong: tampilkan placeholder -->
                <tr v-if="!rows.length">
                    <td colspan="2" class="px-4 py-6 text-center text-slate-400">Belum ada data.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

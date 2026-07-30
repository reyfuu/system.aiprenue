<script setup>
// Kartu statistik ringkas. Mode `accent` = kartu gradient brand (dipakai untuk Laba/Rugi).
defineProps({
    label: String, // teks label di atas
    value: String, // nilai utama (sudah diformat, mis. hasil rp())
    hint: String, // teks kecil opsional di bawah nilai
    accent: { type: Boolean, default: false }, // true = varian gradient brand
});
</script>

<template>
    <!-- Varian accent: kartu gradient brand dengan teks putih -->
    <div v-if="accent" class="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
        <p class="text-xs text-brand-100 font-medium">{{ label }}</p>
        <p class="text-lg font-bold mt-1">{{ value }}</p>
        <p v-if="hint" class="text-[10px] text-brand-200 mt-0.5">{{ hint }}</p>
    </div>

    <!-- Varian default: kartu putih dengan border brand -->
    <div v-else class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
        <p class="text-xs text-slate-500 font-medium">{{ label }}</p>
        <p class="text-lg font-bold text-brand-700 mt-1">{{ value }}</p>
        <p v-if="hint" class="text-[10px] text-slate-400 mt-0.5">{{ hint }}</p>
    </div>
</template>

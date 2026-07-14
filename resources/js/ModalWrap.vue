<script setup>
// Bungkus modal: overlay gelap + kartu tengah. Klik luar → emit close; klik dalam dicegah.
defineProps({
    width: { type: String, default: 'max-w-md' },        // lebar kartu modal
    align: { type: String, default: 'items-center' },     // posisi vertikal (center / start)
});
const emit = defineEmits(['close']);                      // event tutup ke induk
</script>

<template>
    <!-- Overlay: klik area gelap menutup modal -->
    <div
        :class="['fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex justify-center overflow-y-auto z-50 p-4', align, align === 'items-start' ? 'py-10' : '']"
        @click="emit('close')"
    >
        <!-- Kartu modal: stop klik agar tak menutup -->
        <div :class="['bg-white rounded-2xl shadow-2xl w-full p-6 border-t-4 border-brand-600', width]" @click.stop>
            <slot />
        </div>
    </div>
</template>

<script setup>
// Galeri Mindmap — daftar semua mindmap + buat baru. Klik → editor.
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import Layout from '../../Layout.vue';
import ModalWrap from '../../ModalWrap.vue';

defineProps({ mindmaps: Array, canManage: Boolean, templates: Array });

// Buat mindmap baru → controller redirect langsung ke editornya.
// `template` menentukan isi awal; struktur nodenya dibangun di server
// (Mindmap::dataDariTemplate), bukan di sini — biar satu sumber kebenaran.
const createForm = useForm({ title: '', template: 'kosong' });
const pilihOpen = ref(false);

const buatDari = (key) => {
    createForm.template = key;
    createForm.post('/mindmaps', { onSuccess: () => (pilihOpen.value = false) });
};

// Hapus
const remove = (m) => { if (confirm(`Hapus mindmap "${m.title}"?`)) router.delete('/mindmaps/' + m.id); };
</script>

<template>
    <Layout title="Mindmap">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-brand-800">Mindmap</h1>
                    <p class="text-sm text-slate-400">{{ mindmaps.length }} mindmap · klik untuk membuka</p>
                </div>
                <button v-if="canManage" @click="pilihOpen = true" :disabled="createForm.processing" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition disabled:opacity-60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Mindmap Baru
                </button>
            </div>

            <!-- Empty state -->
            <div v-if="mindmaps.length === 0" class="bg-white border border-dashed border-brand-200 rounded-2xl p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-brand-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6a2 2 0 11-4 0 2 2 0 014 0zm12 0a2 2 0 11-4 0 2 2 0 014 0zm0 12a2 2 0 11-4 0 2 2 0 014 0zM8 6h4a2 2 0 012 2m0 8a2 2 0 00-2-2H8" /></svg>
                <p class="text-slate-500 font-medium">Belum ada mindmap.</p>
                <p class="text-sm text-slate-400 mb-4">Buat mindmap pertamamu untuk mulai memetakan ide.</p>
                <button v-if="canManage" @click="pilihOpen = true" :disabled="createForm.processing" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition disabled:opacity-60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Mindmap Baru
                </button>
            </div>

            <!-- Grid mindmap -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="m in mindmaps" :key="m.id" class="relative group">
                    <Link :href="`/mindmaps/${m.id}`" class="block bg-white border border-brand-100 rounded-2xl shadow-sm hover:shadow-md hover:border-brand-300 transition p-5 h-full">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-brand-50 text-brand-600 mb-8">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6a2 2 0 11-4 0 2 2 0 014 0zm12 0a2 2 0 11-4 0 2 2 0 014 0zm0 12a2 2 0 11-4 0 2 2 0 014 0zM8 6h4a2 2 0 012 2m0 8a2 2 0 00-2-2H8" /></svg>
                        </span>
                        <p class="font-bold text-slate-700 leading-snug mb-1 truncate">{{ m.title }}</p>
                        <p class="text-xs text-slate-400">{{ m.owner || '—' }} · {{ m.updated }}</p>
                    </Link>
                    <button v-if="canManage" @click.stop.prevent="remove(m)" title="Hapus" class="absolute top-3 right-3 w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                    </button>
                </div>
            </div>

            <!-- Pilih template. Sengaja modal, bukan langsung buat: sekali diklik
                 mindmap-nya LANGSUNG dibuat & tersimpan, jadi pilihannya harus
                 terlihat dulu — bukan menebak-nebak lalu menghapus yang salah. -->
            <ModalWrap v-if="pilihOpen" width="max-w-2xl" @close="pilihOpen = false">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-brand-800">Mulai dari mana?</h2>
                        <p class="text-sm text-slate-400">Pilih kerangka, isinya bisa diubah setelahnya.</p>
                    </div>
                    <button @click="pilihOpen = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button
                        v-for="t in templates"
                        :key="t.key"
                        @click="buatDari(t.key)"
                        :disabled="createForm.processing"
                        class="text-left border border-brand-100 hover:border-brand-400 hover:bg-brand-50/60 rounded-xl p-4 transition disabled:opacity-60"
                    >
                        <p class="font-semibold text-slate-700 text-sm mb-0.5">{{ t.label }}</p>
                        <p class="text-xs text-slate-400 leading-snug">{{ t.desc }}</p>
                    </button>
                </div>

                <p v-if="createForm.processing" class="text-xs text-slate-400 mt-3">Membuat mindmap…</p>
            </ModalWrap>
        </div>
    </Layout>
</template>

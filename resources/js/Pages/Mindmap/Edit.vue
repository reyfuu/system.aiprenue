<script setup>
// Editor Mindmap — mind-elixir di kanvas + toolbar (judul, Save, Share, Hapus).
// Save via fetch (bukan Inertia) supaya kanvas tak re-render/reset saat menyimpan.
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import MindElixir from 'mind-elixir';
import 'mind-elixir/style';
import Layout from '../../Layout.vue';

const props = defineProps({ mindmap: Object, canManage: Boolean });

const mapEl = ref(null);          // div kontainer kanvas
const title = ref(props.mindmap.title);
const saving = ref(false);
const savedAt = ref('');          // jam terakhir simpan
let mind = null;                  // instance mind-elixir

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

onMounted(() => {
    mind = new MindElixir({
        el: mapEl.value,
        direction: MindElixir.SIDE,   // cabang ke dua sisi
        draggable: true,              // node bisa di-drag
        contextMenu: true,            // klik-kanan: add child/parent/sibling, dll
        toolBar: true,                // toolbar zoom/layout kiri-bawah
        nodeMenu: true,               // menu styling node (warna/font)
        keypress: true,               // shortcut (tab=child, enter=sibling, del=hapus)
    });
    // Data tersimpan (dari getData) atau struktur default bila mindmap baru
    const data = props.mindmap.data || MindElixir.new(title.value || 'Mindmap');
    mind.init(data);
});

onBeforeUnmount(() => { mind = null; }); // div dilepas otomatis saat unmount

// Simpan judul + struktur node ke server
const save = async () => {
    if (!props.canManage || !mind) return;
    saving.value = true;
    try {
        const res = await fetch(`/mindmaps/${props.mindmap.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ title: title.value.trim() || 'Mindmap', data: mind.getData() }),
        });
        if (res.ok) savedAt.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    } finally {
        saving.value = false;
    }
};

// Share v1: salin link editor ke clipboard
const shared = ref(false);
const share = async () => {
    try { await navigator.clipboard.writeText(window.location.href); shared.value = true; setTimeout(() => (shared.value = false), 2000); } catch { /* abaikan */ }
};

const remove = () => { if (props.canManage && confirm(`Hapus mindmap "${title.value}"?`)) router.delete('/mindmaps/' + props.mindmap.id); };
</script>

<template>
    <Layout :title="title">
        <div class="p-6">
            <!-- Toolbar -->
            <div class="bg-white border border-brand-100 rounded-2xl shadow-sm p-3 mb-3 flex items-center gap-3">
                <Link href="/mindmaps" title="Semua mindmap" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-brand-700 pr-2 border-r border-slate-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Mindmap
                </Link>
                <input v-model="title" :disabled="!canManage" @keydown.enter="save" class="flex-1 max-w-md text-lg font-bold text-brand-800 bg-transparent border-b border-transparent hover:border-slate-200 focus:border-brand-400 outline-none disabled:opacity-100 py-1" />

                <span v-if="savedAt" class="text-xs text-slate-400">tersimpan {{ savedAt }}</span>

                <div class="ml-auto flex items-center gap-2">
                    <button @click="share" class="inline-flex items-center gap-1.5 border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-semibold px-3 py-2 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.7 10.7l6.6-3.4M8.7 13.3l6.6 3.4M18 8a3 3 0 100-6 3 3 0 000 6zM6 15a3 3 0 100-6 3 3 0 000 6zm12 7a3 3 0 100-6 3 3 0 000 6z" /></svg>
                        {{ shared ? 'Link disalin!' : 'Share' }}
                    </button>
                    <button v-if="canManage" @click="save" :disabled="saving" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition disabled:opacity-60">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        {{ saving ? 'Menyimpan…' : 'Save' }}
                    </button>
                    <button v-if="canManage" @click="remove" title="Hapus mindmap" class="inline-flex items-center gap-1.5 border border-red-200 text-red-600 hover:bg-red-50 text-sm font-semibold px-3 py-2 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                    </button>
                </div>
            </div>

            <!-- Kanvas mind-elixir -->
            <div ref="mapEl" class="mindmap-canvas w-full h-[calc(100vh-11rem)] rounded-2xl border border-brand-100 bg-white overflow-hidden"></div>
            <p v-if="!canManage" class="text-xs text-slate-400 mt-2">Mode lihat — hanya owner/manager/it yang bisa mengubah & menyimpan.</p>
        </div>
    </Layout>
</template>

<style scoped>
/* Pastikan kanvas mind-elixir mengisi kontainer */
.mindmap-canvas :deep(me-main),
.mindmap-canvas :deep(.map-container) { width: 100%; height: 100%; }
</style>

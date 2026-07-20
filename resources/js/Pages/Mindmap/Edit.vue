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

// Tema kustom — bawaan mind-elixir tampilannya polos (kotak siku, abu-abu, rapat).
// Yang diubah: node membulat & berjarak, root jadi pil brand, palet cabang cerah
// tapi tetap serasi dgn biru elektrik aplikasi (#2c4bff).
const TEMA = {
    name: 'aipreneur',
    type: 'light',
    // Warna cabang utama — dipakai bergiliran per cabang. Urutannya sengaja
    // selang-seling gelap/terang biar cabang bersebelahan tak menyatu.
    palette: ['#2c4bff', '#f5576c', '#10b981', '#f59e0b', '#8b5cf6', '#0ea5e9', '#ec4899', '#14b8a6'],
    cssVar: {
        '--node-gap-x': '32px',       // bawaan terlalu rapat → terlihat sesak
        '--node-gap-y': '12px',
        '--main-gap-x': '58px',
        '--main-gap-y': '22px',
        '--main-color': '#191a2b',    // teks cabang utama = near-black brand
        '--main-bgcolor': '#ffffff',
        '--main-border': '1px solid #dfe4ff',
        '--color': '#334155',         // teks node anak
        '--bgcolor': '#ffffff',
        '--selected': '#2c4bff',      // garis pilih = warna brand
        '--accent-color': '#2c4bff',
        '--root-color': '#ffffff',    // root: pil biru brand, jadi pusat jelas
        '--root-bgcolor': '#2c4bff',
        '--root-border-color': '#2c4bff',
        '--root-radius': '14px',
        '--main-radius': '10px',
        '--topic-padding': '9px 14px',  // bawaan terlalu tipis → teks nempel tepi
        '--panel-color': '#334155',
        '--panel-bgcolor': '#ffffff',
        '--panel-border-color': '#dfe4ff',
        '--map-padding': '60px',
    },
};

onMounted(() => {
    mind = new MindElixir({
        el: mapEl.value,
        direction: MindElixir.SIDE,   // cabang ke dua sisi
        draggable: true,              // node bisa di-drag
        contextMenu: true,            // klik-kanan: add child/parent/sibling, dll
        toolBar: true,                // toolbar zoom/layout kiri-bawah
        nodeMenu: true,               // menu styling node (warna/font)
        keypress: true,               // shortcut (tab=child, enter=sibling, del=hapus)
        theme: TEMA,
    });
    // Data tersimpan (dari getData) atau struktur default bila mindmap baru
    const data = props.mindmap.data || MindElixir.new(title.value || 'Mindmap');

    // getData() IKUT menyimpan tema, jadi mindmap yang dibuat sebelum tema ini ada
    // membawa tema lama & akan menimpa TEMA di atas — tampilannya tetap polos
    // padahal sudah diperbaiki. Ditimpa di sini supaya semua peta seragam.
    data.theme = TEMA;

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

            <!-- Kanvas mind-elixir. Latar titik-titik (bukan putih polos) supaya
                 terasa papan tulis & geseran kanvas kelihatan bergerak. -->
            <div ref="mapEl" class="mindmap-canvas w-full h-[calc(100vh-11rem)] rounded-2xl border border-brand-100 overflow-hidden"></div>
            <p v-if="!canManage" class="text-xs text-slate-400 mt-2">Mode lihat — hanya owner/manager/it yang bisa mengubah & menyimpan.</p>
        </div>
    </Layout>
</template>

<style scoped>
/* Pastikan kanvas mind-elixir mengisi kontainer */
.mindmap-canvas :deep(me-main),
.mindmap-canvas :deep(.map-container) { width: 100%; height: 100%; }

/* Latar titik-titik ala papan tulis. Ditaruh di kontainer (bukan .map-container)
   supaya tak ikut bergeser saat kanvas di-pan — titiknya jadi acuan diam. */
.mindmap-canvas {
    background-color: #fbfbfd;
    background-image: radial-gradient(#d7dcf0 1px, transparent 1px);
    background-size: 22px 22px;
}
.mindmap-canvas :deep(me-main),
.mindmap-canvas :deep(.map-container) { background: transparent; }

/* Node: bayangan halus + transisi. Bawaan mind-elixir rata tanpa kedalaman,
   itu penyebab utama tampilannya terasa "datar & basic". */
.mindmap-canvas :deep(me-tpc) {
    box-shadow: 0 1px 2px rgb(25 26 43 / 6%), 0 2px 8px rgb(25 26 43 / 5%);
    transition: box-shadow .15s ease, transform .15s ease;
}
.mindmap-canvas :deep(me-tpc:hover) {
    box-shadow: 0 2px 6px rgb(44 75 255 / 14%), 0 6px 16px rgb(44 75 255 / 10%);
}
/* Root sedikit lebih tebal & menonjol — pusat peta harus langsung ketemu mata */
.mindmap-canvas :deep(me-root me-tpc) {
    font-weight: 700;
    letter-spacing: .01em;
    box-shadow: 0 3px 10px rgb(44 75 255 / 30%);
}
/* Node terpilih: cincin brand, bukan garis tipis bawaan */
.mindmap-canvas :deep(me-tpc.selected) {
    box-shadow: 0 0 0 2px #2c4bff, 0 4px 12px rgb(44 75 255 / 18%);
}

/* Panel/toolbar bawaan: samakan dgn bahasa visual aplikasi (membulat + bayangan).
   Nama kelas DIVERIFIKASI ke MindElixir.css — `.node-menu` tak pernah ada di
   library ini, jadi aturan yang menyebutnya cuma mati diam-diam. */
.mindmap-canvas :deep(.mind-elixir-toolbar),
.mindmap-canvas :deep(.context-menu),
.mindmap-canvas :deep(.menu-list) {
    border-radius: 12px;
    border: 1px solid #dfe4ff;
    box-shadow: 0 4px 16px rgb(25 26 43 / 8%);
}
</style>

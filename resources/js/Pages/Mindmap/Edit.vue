<script setup>
// Editor Mindmap — simple-mind-map di kanvas + toolbar edit dan simpan.
// Save via fetch (bukan Inertia) supaya kanvas tak re-render/reset saat menyimpan.
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Layout from '../../Layout.vue';

const props = defineProps({ mindmap: Object, canManage: Boolean });

const mapEl = ref(null);          // div kontainer kanvas
const title = ref(props.mindmap.title);
const saving = ref(false);
const savedAt = ref('');          // jam terakhir simpan
let mind = null;                  // instance simple-mind-map
let dragRegistered = false;       // plugin Drag diregistrasi sekali (global pada konstruktor)
const selected = ref(false);
const ctx = ref({ show: false, x: 0, y: 0 }); // menu klik-kanan pada node

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

// Tema memakai konektor kurva SVG yang dihitung oleh layout engine yang sama
// dengan posisi node. Karena itu ujung garis selalu mengikuti parent dan child.
const TEMA = {
    lineStyle: 'curve',
    lineWidth: 2,
    lineColor: '#a5b4fc',
    backgroundColor: 'transparent',
    rootLineKeepSameInCurve: true,
    rootLineStartPositionKeepSameInCurve: true,
    root: {
        fillColor: '#2c4bff',
        color: '#ffffff',
        fontSize: 17,
        fontWeight: 'bold',
        borderRadius: 14,
        paddingX: 18,
        paddingY: 11,
    },
    second: {
        marginX: 72,
        marginY: 24,
        fillColor: '#ffffff',
        color: '#191a2b',
        borderColor: '#c7d2fe',
        borderWidth: 1,
        borderRadius: 10,
        paddingX: 14,
        paddingY: 9,
    },
    node: {
        marginX: 54,
        marginY: 16,
        fillColor: '#ffffff',
        color: '#334155',
        borderColor: '#dfe4ff',
        borderWidth: 1,
        borderRadius: 9,
        paddingX: 12,
        paddingY: 8,
    },
};

// Peta lama disimpan dalam format MindElixir. Konversi rekursif ini membuat
// semua data lama langsung terbaca; setelah Save formatnya menjadi format baru.
const ubahNodeLama = (node) => ({
    data: {
        uid: node.id,
        text: node.topic || 'Topik',
        expand: node.expanded !== false,
        ...(node.direction === 0 ? { dir: 'left' } : node.direction === 1 ? { dir: 'right' } : {}),
    },
    children: (node.children || []).map(ubahNodeLama),
});

const dataAwal = () => {
    const data = props.mindmap.data;
    if (data?.nodeData) return ubahNodeLama(data.nodeData);
    if (data?.data && Array.isArray(data.children)) return data;
    return { data: { text: title.value || 'Mindmap', expand: true }, children: [] };
};

onMounted(async () => {
    // Library cukup besar, jadi muat hanya saat editor dibuka agar halaman lain
    // tidak ikut mengunduh mesin mindmap.
    const { default: MindMap } = await import('simple-mind-map');
    // Plugin Drag: node bisa di-grab untuk dipindah/disusun ulang. Diregistrasi sekali
    // (usePlugin global pada konstruktor); otomatis nonaktif saat readonly (view-only).
    const { default: Drag } = await import('simple-mind-map/src/plugins/Drag.js');
    if (!dragRegistered) { MindMap.usePlugin(Drag); dragRegistered = true; }
    mind = new MindMap({
        el: mapEl.value,
        data: dataAwal(),
        layout: 'mindMap',
        readonly: !props.canManage,
        themeConfig: TEMA,
        fit: true,
        enableAutoEnterTextEditWhenKeydown: true,
        selectTextOnEnterEditText: true,
        defaultInsertSecondLevelNodeText: 'Topik baru',
        defaultInsertBelowSecondLevelNodeText: 'Subtopik baru',
    });
    mind.on('node_active', (_node, nodes) => { selected.value = nodes.length > 0; });
    // Klik-kanan node → menu tambah child/sibling/hapus. Node yang diklik dijadikan
    // aktif dulu supaya execCommand (yang bekerja pada node aktif) mengenainya.
    mind.on('node_contextmenu', (e, node) => {
        if (!props.canManage) return;
        e.preventDefault?.();
        mind.execCommand('SET_NODE_ACTIVE', node, true);
        selected.value = true;
        ctx.value = { show: true, x: e.clientX, y: e.clientY };
    });
});

onBeforeUnmount(() => { mind?.destroy(); mind = null; });

// Aksi dasar ada di toolbar (mudah ditemukan) DAN via klik-kanan node (menu ctx).
// Shortcut: Tab, Enter, Delete, dan Ctrl+I.
const command = (name) => { if (props.canManage && mind) mind.execCommand(name); };
const ctxAction = (name) => { command(name); ctx.value.show = false; };
const fit = () => mind?.view.fit();
const zoom = (arah) => arah > 0 ? mind?.view.enlarge() : mind?.view.narrow();

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
                    <div v-if="canManage" class="flex items-center rounded-lg border border-slate-200 overflow-hidden">
                        <button @click="command('INSERT_CHILD_NODE')" :disabled="!selected" title="Tambah child (Tab)" class="map-tool">+ Child</button>
                        <button @click="command('INSERT_NODE')" :disabled="!selected" title="Tambah sibling (Enter)" class="map-tool border-l border-slate-200">+ Sibling</button>
                        <button @click="command('REMOVE_NODE')" :disabled="!selected" title="Hapus node (Delete)" class="map-tool border-l border-slate-200 text-red-600">Hapus node</button>
                    </div>
                    <div class="flex items-center rounded-lg border border-slate-200 overflow-hidden">
                        <button @click="zoom(-1)" title="Perkecil" class="map-tool">−</button>
                        <button @click="fit" title="Pas ke layar (Ctrl+I)" class="map-tool border-x border-slate-200">Fit</button>
                        <button @click="zoom(1)" title="Perbesar" class="map-tool">+</button>
                    </div>
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

            <!-- Kanvas simple-mind-map. Latar titik-titik (bukan putih polos) supaya
                 terasa papan tulis & geseran kanvas kelihatan bergerak. -->
            <div ref="mapEl" @contextmenu.prevent class="mindmap-canvas w-full h-[calc(100vh-11rem)] rounded-2xl border border-brand-100 overflow-hidden"></div>

            <!-- Menu klik-kanan node (owner/manager/it). Backdrop transparan menutup menu saat klik di luar. -->
            <template v-if="canManage && ctx.show">
                <div class="fixed inset-0 z-40" @click="ctx.show = false" @contextmenu.prevent="ctx.show = false"></div>
                <div class="fixed z-50 min-w-[9rem] bg-white border border-slate-200 rounded-lg shadow-lg py-1 text-sm" :style="{ left: ctx.x + 'px', top: ctx.y + 'px' }">
                    <button @click="ctxAction('INSERT_CHILD_NODE')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700">+ Tambah child</button>
                    <button @click="ctxAction('INSERT_NODE')" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 text-slate-700">+ Tambah sibling</button>
                    <button @click="ctxAction('REMOVE_NODE')" class="w-full text-left px-3 py-1.5 hover:bg-red-50 text-red-600">Hapus node</button>
                </div>
            </template>
            <p v-if="!canManage" class="text-xs text-slate-400 mt-2">Mode lihat — hanya owner/manager/it yang bisa mengubah & menyimpan.</p>
        </div>
    </Layout>
</template>

<style scoped>
.map-tool {
    padding: .5rem .7rem;
    color: #475569;
    font-size: .75rem;
    font-weight: 600;
    transition: background-color .15s;
}
.map-tool:hover { background: #f8fafc; }
.map-tool:disabled { cursor: not-allowed; opacity: .35; }

/* Latar titik-titik ala papan tulis. Ditaruh di kontainer (bukan .map-container)
   supaya tak ikut bergeser saat kanvas di-pan — titiknya jadi acuan diam. */
.mindmap-canvas {
    background-color: #fbfbfd;
    background-image: radial-gradient(#d7dcf0 1px, transparent 1px);
    background-size: 22px 22px;
}
</style>

<script setup>
// Halaman Kanban (Vue) — kolom dinamis, drag-drop, label, checklist, + fitur kartu:
// deadline, arsip, deskripsi, attachment, komentar (staff yg ditugasi pun bisa komentar).
import { ref, computed, watch } from 'vue';                       // reaktivitas Vue
import { router, useForm, usePage } from '@inertiajs/vue3';        // Inertia: navigasi, form, props
import Layout from '../Layout.vue';                                // kerangka + sidebar
import ModalWrap from '../ModalWrap.vue';                          // pembungkus modal

// Props dari controller
const props = defineProps({
    category: String, counts: Object, categories: Object, board: Object, columns: Array,
    staff: Array, outputs: Array, canManage: Boolean, currentBoard: Object,
    showArchived: Boolean, archivedCount: Number,
});

// Preset label warna (Urgent = penanda mendesak)
const LABEL_PRESETS = [
    { name: 'Urgent', color: 'bg-red-500' },
    { name: 'Penting', color: 'bg-amber-500' },
    { name: 'Review', color: 'bg-purple-500' },
    { name: 'Selesai', color: 'bg-emerald-500' },
    { name: 'Info', color: 'bg-sky-500' },
];

const authUser = usePage().props.auth.user;                        // user login (izin hapus komentar)
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || ''; // token utk fetch
const todayStr = () => new Date().toISOString().slice(0, 10);      // 'YYYY-MM-DD' hari ini
const isUrgent = (card) => (card.labels || []).some((l) => l.name === 'Urgent'); // kartu mendesak?
const fmtSize = (b) => (b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'); // ukuran file

// State kartu per kolom (salinan board; di-resync bila board prop berubah)
const cols = ref({ ...props.board });
watch(() => props.board, (b) => { cols.value = { ...b }; });      // sinkron ulang saat Inertia kirim board baru

const q = ref('');                                                 // teks filter
const drag = ref({ id: null, from: null });                        // state drag aktif
const colMenu = ref(null);                                         // kolom yg menunya terbuka
const colNames = computed(() => Object.fromEntries(props.columns.map((c) => [c.key, c.name]))); // key→nama kolom

// Filter kartu per kolom (cocokkan endorse/code)
const filtered = (key) => {
    const list = cols.value[key] || [];
    const s = q.value.trim().toLowerCase();
    if (!s) return list;
    return list.filter((c) => c.endorse.toLowerCase().includes(s) || c.code.toLowerCase().includes(s));
};

// ---- Drag & drop (nonaktif di mode arsip) ----
const onDragStart = (id, from) => { if (props.canManage && !props.showArchived) drag.value = { id, from }; };
const onDrop = (to) => {
    if (!props.canManage || props.showArchived) return;
    const { id, from } = drag.value;
    if (id === null || from === to) return;
    const card = (cols.value[from] || []).find((c) => c.id === id);
    if (!card) return;
    // Pindahkan kartu antar kolom (immutable)
    cols.value = { ...cols.value, [from]: cols.value[from].filter((c) => c.id !== id), [to]: [...cols.value[to], card] };
    drag.value = { id: null, from: null };
    // Simpan progress ke server (fire-and-forget; reload bila gagal)
    fetch(`/pipelines/${id}/progress`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify({ progress: to }),
    }).catch(() => router.reload());
};

// ---- Modal tambah kartu ----
const addOpen = ref(false);
// NB: field kolom dinamai `progressKey` (bukan `progress`) agar tak bentrok
// dengan properti bawaan useForm (`form.progress` = progres upload). Dipetakan
// ke `progress` saat submit lewat transform().
const addForm = useForm({ category: props.category, progressKey: props.columns[0]?.key || 'script', endorse: '', account: 'fk', assigned_to: '', link: '', payment_status: 'belum', ke_gilang: 'belum' });
const openAdd = (progress) => {
    if (!props.canManage) return;
    addForm.reset();                    // kembalikan ke default
    addForm.progressKey = progress;     // set kolom tujuan
    addOpen.value = true;
};
const submitAdd = () => addForm
    .transform(({ progressKey, ...rest }) => ({ ...rest, progress: progressKey })) // progressKey → progress
    .post('/pipelines', { onSuccess: () => (addOpen.value = false) });

// ---- Modal DETAIL kartu (klik kartu; untuk semua user) ----
const detailId = ref(null);            // id kartu dibuka
const detailCard = computed(() => (detailId.value ? Object.values(cols.value).flat().find((c) => c.id === detailId.value) : null));
// Form edit (khusus manager)
// `progressKey` (bukan `progress`) — hindari bentrok properti bawaan useForm.
const editForm = useForm({ category: props.category, endorse: '', description: '', account: 'fk', progressKey: 'script', assigned_to: '', payment_status: 'belum', amount_idr: '', amount_usd: '', link: '', deadline: '', outputs: [], notes: '', ke_gilang: 'belum', labels: [] });
const openDetail = (card) => {
    detailId.value = card.id;
    if (props.canManage) {             // isi form edit dari kartu
        editForm.category = props.category;
        editForm.endorse = card.endorse ?? '';
        editForm.description = card.description ?? '';
        editForm.account = card.account_key ?? 'fk';
        editForm.progressKey = card.progress ?? 'script';
        editForm.assigned_to = card.assigned_to ?? '';
        editForm.payment_status = card.payment_status ?? 'belum';
        editForm.amount_idr = card.amount_idr ?? '';
        editForm.amount_usd = card.amount_usd ?? '';
        editForm.link = card.link ?? '';
        editForm.deadline = card.deadline ?? '';
        editForm.outputs = Array.isArray(card.output_ids) ? card.output_ids.map(Number) : [];
        editForm.notes = card.notes ?? '';
        editForm.ke_gilang = card.ke_gilang ?? 'belum';
        editForm.labels = Array.isArray(card.labels) ? card.labels.map((l) => ({ ...l })) : [];
    }
};
const submitEdit = () => editForm
    .transform(({ progressKey, ...rest }) => ({ ...rest, progress: progressKey })) // progressKey → progress
    .put('/pipelines/' + detailId.value, { preserveScroll: true });
const hasLabel = (color) => editForm.labels.some((l) => l.color === color);
const toggleLabel = (lp) => {
    const i = editForm.labels.findIndex((l) => l.color === lp.color);
    const next = [...editForm.labels];
    if (i === -1) next.push({ name: lp.name, color: lp.color }); else next.splice(i, 1);
    editForm.labels = next;
};
const toggleOutput = (id) => {
    editForm.outputs = editForm.outputs.includes(id) ? editForm.outputs.filter((x) => x !== id) : [...editForm.outputs, id];
};

// ---- Arsip / hapus kartu ----
const archiveCard = (card) => { if (props.canManage) router.patch(`/pipelines/${card.id}/archive`, {}, { preserveScroll: true, onSuccess: () => (detailId.value = null) }); };
const deleteCard = (card) => {
    if (!props.canManage) return;
    if (!confirm(`Hapus kartu "${card.endorse}"? Tindakan ini tidak bisa dibatalkan.`)) return;
    router.delete('/pipelines/' + card.id, { onSuccess: () => (detailId.value = null) });
};

// ---- Komentar (semua user boleh) ----
const commentForm = useForm({ body: '' });
const submitComment = () => {
    if (!commentForm.body.trim()) return;
    commentForm.post(`/pipelines/${detailId.value}/comments`, { preserveScroll: true, onSuccess: () => commentForm.reset('body') });
};
const deleteComment = (id) => router.delete(`/comments/${id}`, { preserveScroll: true });

// ---- Lampiran (upload manager; unduh semua) ----
const attachForm = useForm({ file: null });
const submitAttach = () => {
    if (!attachForm.file) return;
    attachForm.post(`/pipelines/${detailId.value}/attachments`, { forceFormData: true, preserveScroll: true, onSuccess: () => attachForm.reset('file') });
};
const deleteAttachment = (id) => router.delete(`/attachments/${id}`, { preserveScroll: true });

// ---- Checklist / todo (modal terpisah) ----
const todoTarget = ref(null);          // {col, id} kartu aktif
const newTodo = ref('');
const todoCard = computed(() => (todoTarget.value ? (cols.value[todoTarget.value.col] || []).find((c) => c.id === todoTarget.value.id) : null));
const todoDone = (card) => (card?.todos || []).filter((t) => t.done).length;
const findCardCol = (id) => Object.keys(cols.value).find((k) => cols.value[k].some((c) => c.id === id));
const openTodo = (card) => { todoTarget.value = { col: findCardCol(card.id), id: card.id }; newTodo.value = ''; };
const saveTodos = (col, id, todos) => {
    cols.value = { ...cols.value, [col]: cols.value[col].map((c) => (c.id === id ? { ...c, todos } : c)) };
    fetch(`/pipelines/${id}/todos`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ todos }) }).catch(() => {});
};
const toggleTodo = (i) => { if (props.canManage && todoCard.value) saveTodos(todoTarget.value.col, todoTarget.value.id, todoCard.value.todos.map((t, idx) => (idx === i ? { ...t, done: !t.done } : t))); };
const addTodo = () => { const t = newTodo.value.trim(); if (props.canManage && todoCard.value && t) { saveTodos(todoTarget.value.col, todoTarget.value.id, [...todoCard.value.todos, { text: t, done: false }]); newTodo.value = ''; } };
const removeTodo = (i) => { if (props.canManage && todoCard.value) saveTodos(todoTarget.value.col, todoTarget.value.id, todoCard.value.todos.filter((_, idx) => idx !== i)); };

// ---- Modal board & kolom ----
const boardCreateOpen = ref(false);
const boardEditOpen = ref(false);
const colCreateOpen = ref(false);
const colEditOpen = ref(false);
const colEditId = ref(null);
const boardForm = useForm({ name: '' });
const colForm = useForm({ board_key: props.category, name: '' });
const submitBoardCreate = () => boardForm.post('/boards', { onSuccess: () => (boardCreateOpen.value = false) });
const submitBoardEdit = () => boardForm.put('/boards/' + props.currentBoard.key, { onSuccess: () => (boardEditOpen.value = false) });
const submitColCreate = () => colForm.post('/columns', { onSuccess: () => (colCreateOpen.value = false) });
const submitColEdit = () => colForm.put('/columns/' + colEditId.value, { onSuccess: () => (colEditOpen.value = false) });
const openColEdit = (id, name) => { colEditId.value = id; colForm.name = name; colEditOpen.value = true; };
const deleteColumn = (id) => { if (props.canManage && confirm('Hapus kolom ini? (hanya bila kosong)')) router.delete('/columns/' + id); };
const deleteBoard = () => { if (confirm(`Hapus board "${props.currentBoard.name}"? (hanya bila kosong)`)) router.delete('/boards/' + props.currentBoard.key); };
const switchBoard = (e) => router.get('/pipelines/kanban', { category: e.target.value }, { preserveState: false });
const toggleArchiveView = () => router.get('/pipelines/kanban', { category: props.category, archived: props.showArchived ? undefined : 1 }, { preserveState: false });
</script>

<template>
    <Layout title="Kanban">
        <div class="p-6">
            <!-- Toolbar board -->
            <div class="bg-white border border-brand-100 rounded-2xl shadow-sm p-4 mb-3 flex items-center gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Board</p>
                    <select :value="category" @change="switchBoard" class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option v-for="(cv, ck) in categories" :key="ck" :value="ck">{{ cv }} · {{ counts[ck] ?? 0 }}</option>
                    </select>
                </div>
                <span class="text-sm text-slate-400 mt-5">{{ counts[category] ?? 0 }} task</span>

                <!-- Aksi board (manager, mode aktif) -->
                <div v-if="canManage && !showArchived" class="flex items-center gap-1.5 mt-5">
                    <button @click="boardForm.name = ''; boardCreateOpen = true" class="inline-flex items-center gap-1 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        Board
                    </button>
                    <template v-if="currentBoard">
                        <button @click="boardForm.name = currentBoard.name; boardEditOpen = true" title="Ubah nama board" class="p-2 rounded-lg text-slate-400 hover:bg-brand-50 hover:text-brand-600 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z" /></svg>
                        </button>
                        <button @click="deleteBoard" title="Hapus board" class="p-2 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                        </button>
                    </template>
                </div>

                <!-- Toggle lihat arsip -->
                <button @click="toggleArchiveView" :class="['ml-auto mt-5 inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-3 py-1.5 border transition', showArchived ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50']">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4" /></svg>
                    {{ showArchived ? 'Lihat aktif' : `Arsip (${archivedCount})` }}
                </button>

                <!-- Badge view-only -->
                <span v-if="!canManage" class="mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 bg-slate-100 border border-slate-200 rounded-full px-3 py-1.5" title="Anda hanya bisa melihat & komentar">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5 5 12 5s9.5 7 9.5 7-2.5 7-9.5 7-9.5-7-9.5-7z" /></svg>
                    Lihat & komentar
                </span>
            </div>

            <!-- Search -->
            <div class="flex items-center gap-3 mb-5">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Search</p>
                    <input v-model="q" placeholder="Filter cards…" class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm w-64 text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-brand-400 outline-none" />
                </div>
                <div class="flex items-center gap-2 mt-5 ml-auto">
                    <button @click="q = ''" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 text-sm px-4 py-2 rounded-lg transition">Clear filters</button>
                    <button @click="router.reload()" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 text-sm px-4 py-2 rounded-lg transition">Refresh</button>
                </div>
            </div>

            <p v-if="showArchived" class="text-sm text-slate-500 mb-3">Menampilkan kartu yang diarsipkan. Buka kartu untuk mengembalikan.</p>

            <!-- Kolom -->
            <div class="overflow-x-auto pb-4">
                <div class="flex gap-3 min-w-max">
                    <div v-for="col in columns" :key="col.key" class="w-72 flex-shrink-0 bg-white border border-brand-100 rounded-2xl shadow-sm p-3" @dragover.prevent @drop="onDrop(col.key)">
                        <!-- Header kolom -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span :class="['w-2.5 h-2.5 rounded-full', col.color]"></span>
                                <h2 class="text-sm font-bold text-slate-700">{{ col.name }}</h2>
                                <span class="text-xs text-slate-400">{{ filtered(col.key).length }}</span>
                            </div>
                            <div v-if="canManage && !showArchived" class="flex items-center gap-0.5">
                                <button @click="openAdd(col.key)" title="Tambah task" class="w-6 h-6 flex items-center justify-center rounded-md bg-brand-50 hover:bg-brand-100 text-brand-600 font-bold leading-none transition">+</button>
                                <div class="relative">
                                    <button @click.stop="colMenu = colMenu === col.key ? null : col.key" title="Menu kolom" class="w-6 h-6 flex items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 transition">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4z" /></svg>
                                    </button>
                                    <div v-if="colMenu === col.key" class="absolute right-0 top-7 z-20 w-36 bg-white border border-brand-100 rounded-xl shadow-lg py-1 text-sm">
                                        <button @click="colMenu = null; openColEdit(col.id, col.name)" class="w-full text-left px-4 py-2 hover:bg-brand-50 text-slate-600">Ubah nama</button>
                                        <button @click="colMenu = null; deleteColumn(col.id)" class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600">Hapus kolom</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Daftar kartu -->
                        <div class="space-y-2.5 min-h-[120px] rounded-xl p-2 bg-brand-50/60">
                            <div
                                v-for="card in filtered(col.key)"
                                :key="card.id"
                                :draggable="canManage && !showArchived"
                                @dragstart="onDragStart(card.id, col.key)"
                                @click="openDetail(card)"
                                :class="['group bg-white border rounded-xl p-3 shadow-sm hover:shadow-md transition', isUrgent(card) ? 'border-red-300 ring-1 ring-red-200' : 'border-brand-100 hover:border-brand-200', showArchived ? 'opacity-70 cursor-pointer' : canManage ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer']"
                            >
                                <!-- Strip label -->
                                <div v-if="card.labels && card.labels.length" class="flex flex-wrap gap-1 mb-1.5">
                                    <span v-for="(lb, li) in card.labels" :key="li" :class="['h-1.5 w-9 rounded-full', lb.color]" :title="lb.name"></span>
                                </div>
                                <!-- Kode + hapus -->
                                <div class="flex items-start justify-between mb-1">
                                    <p class="text-[10px] text-slate-400 font-mono">{{ card.code }}</p>
                                    <button v-if="canManage" @click.stop="deleteCard(card)" title="Hapus kartu" class="text-slate-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                                <p class="font-semibold text-sm text-slate-700 leading-snug mb-2">{{ card.endorse }}</p>

                                <!-- Meta: urgent, deadline, deskripsi, komentar, lampiran -->
                                <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                    <span v-if="isUrgent(card)" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-500 text-white">URGENT</span>
                                    <span v-if="card.deadline" :class="['inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded', card.deadline < todayStr() ? 'bg-red-100 text-red-700 font-semibold' : 'bg-slate-100 text-slate-600']">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" /></svg>
                                        {{ card.deadline }}
                                    </span>
                                    <span v-if="card.description" class="inline-flex items-center text-slate-400" title="Ada deskripsi"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" /></svg></span>
                                    <span v-if="card.comment_count > 0" class="inline-flex items-center gap-0.5 text-[10px] text-slate-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 01-13.5 7.8L3 21l1.2-4.5A9 9 0 1121 12z" /></svg>{{ card.comment_count }}</span>
                                    <span v-if="card.attachment_count > 0" class="inline-flex items-center gap-0.5 text-[10px] text-slate-400"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>{{ card.attachment_count }}</span>
                                </div>

                                <!-- Output tags -->
                                <div v-if="card.outputs.length" class="flex flex-wrap gap-1 mb-2">
                                    <span v-for="o in card.outputs" :key="o" class="text-[10px] px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 border border-brand-200">{{ o }}</span>
                                </div>

                                <!-- Checklist ringkas -->
                                <button type="button" @click.stop="openTodo(card)" class="w-full flex items-center gap-1.5 text-[10px] text-slate-500 hover:text-brand-700 mb-2 group/todo">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7l2 2 4-4" /></svg>
                                    <span class="font-medium tabular-nums">{{ card.todos.length ? todoDone(card) + '/' + card.todos.length : 'checklist' }}</span>
                                    <span v-if="card.todos.length" class="flex-1 h-1 rounded-full bg-brand-50 overflow-hidden"><span class="block h-full bg-emerald-500 transition-all" :style="{ width: Math.round(todoDone(card) / card.todos.length * 100) + '%' }"></span></span>
                                    <span v-else class="text-brand-400 opacity-0 group-hover/todo:opacity-100 transition">+ tambah</span>
                                </button>

                                <!-- Badge akun + pembayaran + waktu -->
                                <div class="flex items-center justify-between text-[10px] mb-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <span :class="['font-semibold px-2 py-0.5 rounded-full', card.account_color]">{{ card.account }}</span>
                                        <span :class="['font-semibold px-2 py-0.5 rounded-full', card.payment_status === 'lunas' ? 'bg-emerald-600 text-white' : card.payment_status === 'dp' ? 'bg-amber-400 text-amber-900' : 'bg-red-600 text-white']">{{ card.payment }}</span>
                                    </div>
                                    <span class="text-slate-400">{{ card.time }}</span>
                                </div>
                                <!-- PJ + link -->
                                <div class="flex items-center justify-between gap-2 text-[10px] pt-1.5 border-t border-brand-50">
                                    <span v-if="card.assignee" class="flex items-center gap-1 text-slate-500 truncate">
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                        <span class="truncate font-medium">{{ card.assignee }}</span>
                                    </span>
                                    <span v-else class="text-slate-300 italic">belum ditugaskan</span>
                                    <a v-if="card.link" :href="card.link" target="_blank" rel="noreferrer" @click.stop class="flex items-center gap-0.5 text-brand-600 hover:text-brand-800 font-medium flex-shrink-0">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                        Link
                                    </a>
                                </div>
                            </div>
                            <p v-if="filtered(col.key).length === 0" class="text-center text-xs text-slate-400 py-6">— no tasks —</p>
                        </div>
                    </div>

                    <!-- Tambah kolom -->
                    <div v-if="canManage && !showArchived" class="w-64 flex-shrink-0">
                        <button @click="colForm.board_key = category; colForm.name = ''; colCreateOpen = true" class="w-full flex items-center gap-2 bg-white/70 hover:bg-white border border-dashed border-brand-200 hover:border-brand-300 text-slate-500 hover:text-brand-700 rounded-2xl px-4 py-3 text-sm font-semibold transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                            Add another list
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Modal DETAIL kartu ===== -->
        <ModalWrap v-if="detailId && detailCard" width="max-w-2xl" align="items-start" @close="detailId = null">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="text-[10px] text-slate-400 font-mono">{{ detailCard.code }}</p>
                    <h2 class="text-lg font-bold text-brand-800 flex items-center gap-2">
                        {{ detailCard.endorse }}
                        <span v-if="isUrgent(detailCard)" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-500 text-white">URGENT</span>
                        <span v-if="detailCard.archived" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-200 text-slate-600">ARSIP</span>
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="canManage" @click="archiveCard(detailCard)" :title="detailCard.archived ? 'Kembalikan dari arsip' : 'Arsipkan kartu'" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                        {{ detailCard.archived ? 'Kembalikan' : 'Arsipkan' }}
                    </button>
                    <button type="button" @click="detailId = null" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                </div>
            </div>

            <!-- Form edit lengkap (manager) -->
            <form v-if="canManage" @submit.prevent="submitEdit" class="grid grid-cols-2 gap-3 text-sm mb-2">
                <label class="col-span-2 block font-medium text-slate-600">Judul / Endorse
                    <input v-model="editForm.endorse" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="col-span-2 block font-medium text-slate-600">Deskripsi
                    <textarea v-model="editForm.description" rows="3" placeholder="Detail task…" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                </label>
                <label class="block font-medium text-slate-600">Deadline
                    <input type="date" v-model="editForm.deadline" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="block font-medium text-slate-600">Kolom
                    <select v-model="editForm.progressKey" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option v-for="c in columns" :key="c.key" :value="c.key">{{ c.name }}</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600">Account
                    <select v-model="editForm.account" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="fk">FK</option>
                        <option value="ai_preneur">AI Preneur</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600">Penanggung Jawab
                    <select v-model="editForm.assigned_to" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">— belum ditugaskan —</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600">Payment
                    <select v-model="editForm.payment_status" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="belum">Belum</option>
                        <option value="dp">DP</option>
                        <option value="lunas">Lunas</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600">Jumlah IDR
                    <input type="number" step="0.01" v-model="editForm.amount_idr" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="block font-medium text-slate-600">Jumlah USD
                    <input type="number" step="0.01" v-model="editForm.amount_usd" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="col-span-2 block font-medium text-slate-600">Link Video
                    <input type="url" v-model="editForm.link" placeholder="https://…" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <!-- Output -->
                <div class="col-span-2">
                    <p class="font-medium text-slate-600 mb-1.5">Output</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="out in outputs" :key="out.id" class="inline-flex items-center gap-1.5 bg-brand-50 border border-brand-100 rounded-lg px-3 py-1.5 cursor-pointer">
                            <input type="checkbox" :checked="editForm.outputs.includes(out.id)" @change="toggleOutput(out.id)" class="accent-brand-600" /> {{ out.name }}
                        </label>
                    </div>
                </div>
                <!-- Label -->
                <div class="col-span-2">
                    <p class="font-medium text-slate-600 mb-1.5">Label</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="lp in LABEL_PRESETS" :key="lp.color" type="button" @click="toggleLabel(lp)" :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition', hasLabel(lp.color) ? 'border-brand-400 bg-brand-50 text-slate-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50']">
                            <span :class="['w-3 h-3 rounded-full', lp.color]"></span><span>{{ lp.name }}</span><span v-if="hasLabel(lp.color)">✓</span>
                        </button>
                    </div>
                </div>
                <label class="col-span-2 block font-medium text-slate-600">Notes
                    <textarea v-model="editForm.notes" rows="2" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                </label>
                <div class="col-span-2 flex justify-end">
                    <button type="submit" :disabled="editForm.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">Simpan perubahan</button>
                </div>
            </form>

            <!-- Ringkasan read-only (non-manager) -->
            <div v-else class="space-y-2 text-sm mb-2">
                <p v-if="detailCard.deadline"><span class="font-medium text-slate-600">Deadline:</span> <span :class="detailCard.deadline < todayStr() ? 'text-red-600 font-semibold' : 'text-slate-700'">{{ detailCard.deadline }}</span></p>
                <p v-if="detailCard.assignee"><span class="font-medium text-slate-600">PJ:</span> {{ detailCard.assignee }}</p>
                <div v-if="detailCard.labels.length" class="flex flex-wrap gap-1.5">
                    <span v-for="(lb, li) in detailCard.labels" :key="li" :class="['text-[10px] text-white font-semibold px-2 py-0.5 rounded', lb.color]">{{ lb.name }}</span>
                </div>
                <div>
                    <p class="font-medium text-slate-600">Deskripsi</p>
                    <p class="text-slate-700 whitespace-pre-line">{{ detailCard.description || '—' }}</p>
                </div>
                <a v-if="detailCard.link" :href="detailCard.link" target="_blank" rel="noreferrer" class="text-brand-600 hover:underline text-sm">Buka link video →</a>
            </div>

            <!-- Lampiran -->
            <div class="border-t border-slate-100 pt-4 mt-2">
                <p class="font-semibold text-slate-700 mb-2 text-sm">Lampiran ({{ detailCard.attachments.length }})</p>
                <div class="space-y-1.5 mb-2">
                    <div v-for="a in detailCard.attachments" :key="a.id" class="flex items-center gap-2 text-sm bg-slate-50 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                        <a :href="a.url" target="_blank" rel="noreferrer" class="flex-1 text-brand-700 hover:underline truncate">{{ a.name }}</a>
                        <span class="text-[10px] text-slate-400">{{ fmtSize(a.size) }}</span>
                        <button v-if="canManage" @click="deleteAttachment(a.id)" class="text-slate-300 hover:text-red-500 text-lg leading-none">&times;</button>
                    </div>
                    <p v-if="detailCard.attachments.length === 0" class="text-xs text-slate-400">Belum ada lampiran.</p>
                </div>
                <form v-if="canManage" @submit.prevent="submitAttach" class="flex items-center gap-2">
                    <!-- input file disembunyikan, dipicu label bergaya tombol -->
                    <input id="attach-file" type="file" @change="attachForm.file = $event.target.files[0]" class="hidden" />
                    <label for="attach-file" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                        Pilih file
                    </label>
                    <span class="flex-1 text-xs text-slate-500 truncate">{{ attachForm.file ? attachForm.file.name : 'Belum ada file dipilih' }}</span>
                    <button type="submit" :disabled="attachForm.processing || !attachForm.file" class="px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold transition disabled:opacity-50">Unggah</button>
                </form>
                <p v-if="attachForm.errors.file" class="text-xs text-red-600 mt-1">{{ attachForm.errors.file }}</p>
            </div>

            <!-- Komentar -->
            <div class="border-t border-slate-100 pt-4 mt-4">
                <p class="font-semibold text-slate-700 mb-2 text-sm">Komentar ({{ detailCard.comments.length }})</p>
                <form @submit.prevent="submitComment" class="flex gap-2 mb-3">
                    <input v-model="commentForm.body" placeholder="Tulis komentar…" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                    <button type="submit" :disabled="commentForm.processing" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition disabled:opacity-60">Kirim</button>
                </form>
                <div class="space-y-2.5 max-h-60 overflow-y-auto">
                    <div v-for="c in detailCard.comments" :key="c.id" class="flex gap-2">
                        <div class="w-7 h-7 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold flex-shrink-0">{{ (c.user || '?').charAt(0).toUpperCase() }}</div>
                        <div class="flex-1 bg-slate-50 rounded-xl px-3 py-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-700">{{ c.user || 'User' }}<span class="ml-2 font-normal text-slate-400">{{ c.time }}</span></span>
                                <button v-if="c.user_id === authUser.id || canManage" @click="deleteComment(c.id)" class="text-slate-300 hover:text-red-500 text-sm leading-none">&times;</button>
                            </div>
                            <p class="text-sm text-slate-700 whitespace-pre-line">{{ c.body }}</p>
                        </div>
                    </div>
                    <p v-if="detailCard.comments.length === 0" class="text-xs text-slate-400">Belum ada komentar.</p>
                </div>
            </div>
        </ModalWrap>

        <!-- ===== Modal tambah task ===== -->
        <ModalWrap v-if="addOpen" width="max-w-md" @close="addOpen = false">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-brand-800">Tambah Task <span class="text-sm font-normal text-slate-400">· {{ colNames[addForm.progress] }}</span></h2>
                <button type="button" @click="addOpen = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>
            <form @submit.prevent="submitAdd" class="space-y-3 text-sm">
                <label class="block font-medium text-slate-600">Judul / Endorse
                    <input v-model="addForm.endorse" required autofocus class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="block font-medium text-slate-600">Account
                    <select v-model="addForm.account" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="fk">FK</option>
                        <option value="ai_preneur">AI Preneur</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600">Penanggung Jawab (Staff)
                    <select v-model="addForm.assigned_to" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">— belum ditugaskan —</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }} ({{ s.role }})</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600">Link Video (opsional)
                    <input type="url" v-model="addForm.link" placeholder="https://…" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="addOpen = false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="addForm.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal checklist ===== -->
        <ModalWrap v-if="todoTarget && todoCard" width="max-w-md" @close="todoTarget = null">
            <div class="flex items-start justify-between mb-1">
                <h2 class="text-lg font-bold text-brand-800">Checklist</h2>
                <button type="button" @click="todoTarget = null" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>
            <p class="text-sm text-slate-500 mb-4 truncate">{{ todoCard.endorse }}</p>
            <div v-if="todoCard.todos.length" class="flex items-center gap-2 mb-3">
                <div class="flex-1 h-2 rounded-full bg-brand-50 overflow-hidden"><div class="h-full bg-emerald-500 transition-all" :style="{ width: Math.round(todoDone(todoCard) / todoCard.todos.length * 100) + '%' }"></div></div>
                <span class="text-xs font-semibold text-slate-500 tabular-nums">{{ todoDone(todoCard) }}/{{ todoCard.todos.length }}</span>
            </div>
            <div class="space-y-1.5 max-h-64 overflow-y-auto mb-3">
                <div v-for="(t, i) in todoCard.todos" :key="i" class="flex items-center gap-2 group/item rounded-lg px-2 py-1.5 hover:bg-brand-50">
                    <input type="checkbox" :checked="t.done" @change="toggleTodo(i)" :disabled="!canManage" class="accent-emerald-600 w-4 h-4 flex-shrink-0 disabled:opacity-60" />
                    <span :class="['flex-1 text-sm', t.done ? 'line-through text-slate-400' : 'text-slate-700']">{{ t.text }}</span>
                    <button v-if="canManage" type="button" @click="removeTodo(i)" class="text-slate-300 hover:text-red-500 opacity-0 group-hover/item:opacity-100 transition text-lg leading-none">&times;</button>
                </div>
                <p v-if="todoCard.todos.length === 0" class="text-center text-sm text-slate-400 py-4">Belum ada item.</p>
            </div>
            <form v-if="canManage" @submit.prevent="addTodo" class="flex gap-2">
                <input v-model="newTodo" placeholder="Tambah item…" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition">Tambah</button>
            </form>
        </ModalWrap>

        <!-- ===== Modal board baru ===== -->
        <ModalWrap v-if="canManage && boardCreateOpen" width="max-w-sm" @close="boardCreateOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Board Baru</h2>
            <form @submit.prevent="submitBoardCreate" class="space-y-3">
                <input v-model="boardForm.name" required autofocus placeholder="Nama board…" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                <div class="flex justify-end gap-2">
                    <button type="button" @click="boardCreateOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal kolom baru ===== -->
        <ModalWrap v-if="canManage && colCreateOpen" width="max-w-sm" @close="colCreateOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Kolom Baru</h2>
            <form @submit.prevent="submitColCreate" class="space-y-3">
                <input v-model="colForm.name" required autofocus placeholder="Nama kolom…" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                <div class="flex justify-end gap-2">
                    <button type="button" @click="colCreateOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal ubah nama kolom ===== -->
        <ModalWrap v-if="canManage && colEditOpen" width="max-w-sm" @close="colEditOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Ubah Nama Kolom</h2>
            <form @submit.prevent="submitColEdit" class="space-y-3">
                <input v-model="colForm.name" required autofocus class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                <div class="flex justify-end gap-2">
                    <button type="button" @click="colEditOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal ubah nama board ===== -->
        <ModalWrap v-if="canManage && currentBoard && boardEditOpen" width="max-w-sm" @close="boardEditOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Ubah Nama Board</h2>
            <form @submit.prevent="submitBoardEdit" class="space-y-3">
                <input v-model="boardForm.name" required autofocus class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                <div class="flex justify-end gap-2">
                    <button type="button" @click="boardEditOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">Simpan</button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

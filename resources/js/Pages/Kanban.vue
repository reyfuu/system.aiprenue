<script setup>
// Halaman Kanban (Vue) — kolom dinamis, drag-drop, label, + fitur kartu:
// deadline, arsip, deskripsi, attachment, komentar (staff yg ditugasi pun bisa komentar).
import { ref, computed, watch } from 'vue';                       // reaktivitas Vue
import { router, useForm, usePage, Link } from '@inertiajs/vue3';  // Inertia: navigasi, form, props, Link
import Layout from '../Layout.vue';                                // kerangka + sidebar
import ModalWrap from '../ModalWrap.vue';                          // pembungkus modal
import draggable from 'vuedraggable';                              // drag-drop kartu (SortableJS) ala Trello

// Props dari controller.
// baseUrl/pageTitle/showGallery membuat halaman ini dipakai dua modul:
// '/pipelines' (Sales Pipeline, tanpa galeri) & '/pipelines/kanban' (Kanban, pakai galeri).
const props = defineProps({
    category: String, counts: Object, categories: Object, board: Object, columns: Array,
    staff: Array, outputs: Array, canManage: Boolean, currentBoard: Object,
    showArchived: Boolean, archivedCount: Number, accounts: Object, jenisList: Object,
    baseUrl: { type: String, default: '/pipelines/kanban' },
    pageTitle: { type: String, default: 'Kanban' },
    showGallery: { type: Boolean, default: true },
    boardType: { type: String, default: 'kanban' },   // tipe board baru yg dibuat dari halaman ini
    rate: { type: Number, default: 0 },               // kurs USD→IDR utk menjumlah nilai deal
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

// PATCH JSON + sinkron ulang bila server menolak.
// fetch() TIDAK reject pada 4xx/5xx — tanpa cek res.ok, kegagalan (403/422/500)
// lolos diam-diam & tampilan beda dgn DB sampai halaman di-reload.
const patchCard = (url, body) =>
    fetch(url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    })
        .then((res) => { if (!res.ok) router.reload(); })
        .catch(() => router.reload());   // gagal jaringan
const fmtSize = (b) => (b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'); // ukuran file

// State kartu per kolom (salinan board; di-resync bila board prop berubah).
// Deep-copy tiap array kolom → cols punya array sendiri, TIDAK berbagi referensi
// dgn prop Inertia. Wajib: SortableJS mutasi array ini via splice saat drag;
// kalau berbagi dgn prop readonly, mutasi gagal & kartu "balik" (drag seolah mati).
const cloneBoard = (b) => Object.fromEntries(Object.entries(b || {}).map(([k, v]) => [k, [...(v || [])]]));
const cols = ref(cloneBoard(props.board));
watch(() => props.board, (b) => { cols.value = cloneBoard(b); }); // sinkron ulang saat Inertia kirim board baru

const colMenu = ref(null);                                         // kolom yg menunya terbuka
const colNames = computed(() => Object.fromEntries(props.columns.map((c) => [c.key, c.name]))); // key→nama kolom
const isTodolist = computed(() => props.category === 'todolist'); // board todolist: sembunyikan nominal IDR/USD & payment

const cardCount = (key) => (cols.value[key] || []).length;         // jml kartu per kolom
const dragDisabled = computed(() => !props.canManage || props.showArchived); // nonaktif saat view-only / mode arsip

// ---- Nilai deal (ala Pipedrive: tiap stage punya total) ----
// Kartu bisa IDR, USD, atau dua-duanya → semua dijumlahkan dalam IDR pakai kurs.
// amount_* datang sbg string (cast decimal:2), jadi wajib Number().
const cardValue = (card) => Number(card.amount_idr || 0) + Number(card.amount_usd || 0) * props.rate;
const colValue = (key) => (cols.value[key] || []).reduce((sum, c) => sum + cardValue(c), 0);
const boardValue = computed(() => Object.values(cols.value).flat().reduce((sum, c) => sum + cardValue(c), 0));
const boardCount = computed(() => Object.values(cols.value).flat().length);

const rp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n));           // penuh: kartu
const rpShort = (n) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(n); // ringkas: header stage

// ---- Drag & drop (vuedraggable) — pindah antar kolom → simpan progress ----
// vuedraggable memutasi cols.value[key] langsung. @change memicu 'added' HANYA
// di kolom penerima → dari situ kita tahu progress baru kartu.
const onCardChange = (evt, toKey) => {
    if (!evt.added) return;                                        // reorder dalam kolom tak perlu disimpan (tak ada kolom urutan)
    const card = evt.added.element;
    patchCard(`/pipelines/${card.id}/progress`, { progress: toKey });
};

// ---- Modal kartu: dipakai untuk BUAT dan EDIT sekaligus ----
// Dulu ada dua modal terpisah — form tambah cuma subset form edit, jadi kartu baru
// harus dibuat dulu lalu dibuka lagi untuk mengisi detailnya. Sekarang satu modal:
// `creating` true = kartu baru (POST), selain itu = edit kartu `detailId` (PUT).
const detailId = ref(null);            // id kartu yang dibuka (null saat membuat)
const creating = ref(false);           // sedang membuat kartu baru?
const detailCard = computed(() => (detailId.value ? Object.values(cols.value).flat().find((c) => c.id === detailId.value) : null));
// `progressKey` (bukan `progress`) — hindari bentrok properti bawaan useForm
// (`form.progress` = progres upload). Dipetakan ke `progress` saat submit.
const editForm = useForm({ category: props.category, endorse: '', jenis: '', description: '', account: 'fk', progressKey: 'script', assigned_to: '', payment_status: 'belum', amount_idr: '', amount_usd: '', link: '', deadline: '', outputs: [], notes: '', ke_gilang: 'belum', labels: [] });

// Isi form dari kartu (atau dari objek kosong saat membuat). Tiap field diisi
// EKSPLISIT — jangan pakai reset(): Inertia v3 menjadikan data submit terakhir
// sebagai `defaults` baru, jadi reset() malah memunculkan kartu yang barusan dibuat.
const fillForm = (card) => {
    editForm.clearErrors();
    editForm.category = props.category;
    editForm.endorse = card.endorse ?? '';
    editForm.jenis = card.jenis ?? '';
    editForm.description = card.description ?? '';
    editForm.account = card.account_key ?? 'fk';
    editForm.progressKey = card.progress ?? props.columns[0]?.key ?? 'script';
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
};

const openAdd = (progress) => {
    if (!props.canManage) return;
    fillForm({ progress });            // kosong, kecuali kolom tujuan
    detailId.value = null;
    creating.value = true;
};
const openDetail = (card) => {
    creating.value = false;
    detailId.value = card.id;
    if (props.canManage) fillForm(card);
};
const closeCard = () => { detailId.value = null; creating.value = false; };

// Tutup modal setelah simpan sukses (samakan dgn arsip/hapus & modal Order).
// Gagal validasi → modal TETAP terbuka supaya form.errors kelihatan.
const submitCard = () => {
    const form = editForm.transform(({ progressKey, ...rest }) => ({ ...rest, progress: progressKey }));
    if (creating.value) {
        form.post('/pipelines', { preserveScroll: true, onSuccess: closeCard });
    } else {
        form.put('/pipelines/' + detailId.value, { preserveScroll: true, onSuccess: closeCard });
    }
};
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

// ---- Modal board & kolom ----
const boardCreateOpen = ref(false);
const boardEditOpen = ref(false);
const colCreateOpen = ref(false);
const colEditOpen = ref(false);
const colEditId = ref(null);
const boardForm = useForm({ name: '', type: props.boardType });
const colForm = useForm({ board_key: props.category, name: '' });
const submitBoardCreate = () => boardForm.post('/boards', { onSuccess: () => (boardCreateOpen.value = false) });
const submitBoardEdit = () => boardForm.put('/boards/' + props.currentBoard.key, { onSuccess: () => (boardEditOpen.value = false) });
const submitColCreate = () => colForm.post('/columns', { onSuccess: () => (colCreateOpen.value = false) });
const submitColEdit = () => colForm.put('/columns/' + colEditId.value, { onSuccess: () => (colEditOpen.value = false) });
const openColEdit = (id, name) => { colEditId.value = id; colForm.name = name; colEditOpen.value = true; };
const deleteColumn = (id) => { if (props.canManage && confirm('Hapus kolom ini? (hanya bila kosong)')) router.delete('/columns/' + id); };
const deleteBoard = () => { if (confirm(`Hapus board "${props.currentBoard.name}"? (hanya bila kosong)`)) router.delete('/boards/' + props.currentBoard.key); };
const switchBoard = (e) => router.get(props.baseUrl, { category: e.target.value }, { preserveState: false });
const toggleArchiveView = () => router.get(props.baseUrl, { category: props.category, archived: props.showArchived ? undefined : 1 }, { preserveState: false });
</script>

<template>
    <Layout :title="pageTitle">
        <div class="p-6">
            <!-- Toolbar board -->
            <div class="bg-white border border-brand-100 rounded-2xl shadow-sm p-4 mb-3 flex items-center gap-3">
                <!-- Balik ke galeri (kanban luar; Sales Pipeline tak punya galeri) -->
                <Link v-if="showGallery" :href="baseUrl" title="Semua board" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-brand-700 mt-5 pr-2 border-r border-slate-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Galeri
                </Link>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Board</p>
                    <select :value="category" @change="switchBoard" class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option v-for="(cv, ck) in categories" :key="ck" :value="ck">{{ cv }} · {{ counts[ck] ?? 0 }}</option>
                    </select>
                </div>
                <!-- Ringkasan board: jml + total nilai (mengembalikan angka omzet yg dulu di tabel) -->
                <span class="text-sm text-slate-400 mt-5">
                    {{ boardCount }} {{ isTodolist ? 'task' : 'deal' }}<template v-if="!isTodolist"> · <span class="font-semibold text-slate-600">{{ rp(boardValue) }}</span></template>
                </span>

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

            <!-- Pembeda mode: Task Aktif (hijau) vs Mode Arsip (amber) -->
            <div :class="['flex items-center gap-2.5 rounded-xl border px-4 py-2.5 mb-5', showArchived ? 'bg-amber-50 border-amber-300' : 'bg-emerald-50 border-emerald-200']">
                <!-- ikon: kotak arsip / papan aktif -->
                <svg v-if="showArchived" class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4" /></svg>
                <svg v-else class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10M4 18h10" /></svg>
                <span :class="['font-bold text-sm', showArchived ? 'text-amber-800' : 'text-emerald-800']">{{ showArchived ? 'Mode Arsip' : 'Task Aktif' }}</span>
                <span :class="['text-xs', showArchived ? 'text-amber-700' : 'text-emerald-700']">{{ showArchived ? `${archivedCount} kartu terarsip · buka kartu untuk mengembalikan` : `${counts[category] ?? 0} kartu aktif di board ini` }}</span>
                <button @click="router.reload()" title="Muat ulang" class="ml-auto inline-flex items-center gap-1 bg-white/70 hover:bg-white border border-slate-200 text-slate-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Refresh
                </button>
            </div>

            <!-- Kolom -->
            <div class="overflow-x-auto pb-4">
                <div class="flex gap-3 min-w-max">
                    <div v-for="col in columns" :key="col.key" class="w-72 flex-shrink-0 bg-white border border-brand-100 rounded-2xl shadow-sm p-3">
                        <!-- Header kolom: nama stage + total nilai & jml deal (ala Pipedrive) -->
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span :class="['w-2.5 h-2.5 rounded-full', col.color]"></span>
                                    <h2 class="text-sm font-bold text-slate-700">{{ col.name }}</h2>
                                </div>
                                <p class="text-xs text-slate-400 mt-0.5 pl-4.5">
                                    <span v-if="!isTodolist" class="font-semibold text-slate-500">{{ rpShort(colValue(col.key)) }}</span>
                                    <span v-if="!isTodolist"> · </span>{{ cardCount(col.key) }} {{ isTodolist ? 'task' : 'deal' }}
                                </p>
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

                        <!-- Daftar kartu (drag via vuedraggable, ala Trello) -->
                        <div class="min-h-[120px] rounded-xl p-2 bg-brand-50/60">
                            <draggable
                                :list="cols[col.key]"
                                :group="{ name: 'kanban' }"
                                item-key="id"
                                :disabled="dragDisabled"
                                class="space-y-2.5 min-h-[80px]"
                                ghost-class="drag-ghost"
                                :animation="180"
                                @change="onCardChange($event, col.key)"
                            >
                                <template #item="{ element: card }">
                                    <div
                                        @click="openDetail(card)"
                                        :class="['group border rounded-xl p-3 shadow-sm hover:shadow-md transition', isUrgent(card) ? 'bg-white border-red-300 ring-1 ring-red-200' : 'bg-white border-brand-100 hover:border-brand-200', showArchived ? 'opacity-70 cursor-pointer' : canManage ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer']"
                                    >
                                <!-- Strip label -->
                                <div v-if="card.labels && card.labels.length" class="flex flex-wrap gap-1 mb-1.5">
                                    <span v-for="(lb, li) in card.labels" :key="li" :class="['h-1.5 w-9 rounded-full', lb.color]" :title="lb.name"></span>
                                </div>
                                <!-- Hapus kartu (muncul saat hover).
                                     Centang & kode kartu sudah tak dipajang di kartu — tandai selesai
                                     pindah ke modal detail, kode kartu tetap ada di judul modal. -->
                                <div v-if="canManage" class="flex justify-end mb-1">
                                    <button @click.stop="deleteCard(card)" title="Hapus kartu" class="p-1 -m-1 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                                <p class="font-semibold text-sm leading-snug mb-2 text-slate-700">{{ card.endorse }}</p>

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

                                <!-- Badge jenis + akun + pembayaran -->
                                <div class="flex items-center gap-1.5 text-[10px] mb-1.5">
                                    <!-- jenis deal: dulu board sendiri, kini atribut kartu -->
                                    <span v-if="card.jenis_label" class="font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">{{ card.jenis_label }}</span>
                                    <span :class="['font-semibold px-2 py-0.5 rounded-full', card.account_color]">{{ card.account }}</span>
                                    <span v-if="!isTodolist" :class="['font-semibold px-2 py-0.5 rounded-full', card.payment_status === 'lunas' ? 'bg-emerald-600 text-white' : card.payment_status === 'dp' ? 'bg-amber-400 text-amber-900' : 'bg-red-600 text-white']">{{ card.payment }}</span>
                                </div>
                                <!-- PJ + nilai deal + link (nilai = info utama kartu, ala Pipedrive) -->
                                <div class="flex items-center justify-between gap-2 text-[10px] pt-1.5 border-t border-brand-50">
                                    <span v-if="card.assignee" class="flex items-center gap-1.5 text-slate-500 truncate">
                                        <span class="w-4 h-4 flex-shrink-0 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-[9px] font-bold">{{ card.assignee.charAt(0).toUpperCase() }}</span>
                                        <span class="truncate font-medium">{{ card.assignee }}</span>
                                    </span>
                                    <span v-else class="text-slate-300 italic">belum ditugaskan</span>
                                    <span class="flex items-center gap-1.5 flex-shrink-0">
                                        <a v-if="card.link" :href="card.link" target="_blank" rel="noreferrer" @click.stop class="flex items-center gap-0.5 text-brand-600 hover:text-brand-800 font-medium">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                            Link
                                        </a>
                                        <span v-if="!isTodolist" :class="['font-bold text-xs', cardValue(card) > 0 ? 'text-slate-700' : 'text-slate-300']">{{ rp(cardValue(card)) }}</span>
                                    </span>
                                </div>
                                    </div>
                                </template>
                            </draggable>
                            <p v-if="cardCount(col.key) === 0" class="text-center text-xs text-slate-400 py-6">— no tasks —</p>
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

        <!-- ===== Modal kartu: buat kartu baru & detail/edit kartu lama ===== -->
        <ModalWrap v-if="creating || detailCard" width="max-w-2xl" align="items-start" @close="closeCard">
            <div class="flex items-start justify-between mb-4">
                <!-- Judul kartu baru: cuma nama kolom tujuan, belum ada kode/status -->
                <div v-if="creating">
                    <h2 class="text-lg font-bold text-brand-800">Kartu Baru <span class="text-sm font-normal text-slate-400">· {{ colNames[editForm.progressKey] }}</span></h2>
                </div>
                <div v-else>
                    <p class="text-[10px] text-slate-400 font-mono">{{ detailCard.code }}</p>
                    <h2 class="text-lg font-bold flex items-center gap-2 text-brand-800">
                        {{ detailCard.endorse }}
                        <span v-if="isUrgent(detailCard)" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-500 text-white no-underline">URGENT</span>
                        <span v-if="detailCard.archived" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 no-underline">ARSIP</span>
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Arsip cuma untuk kartu yang sudah ada -->
                    <button v-if="canManage && detailCard" @click="archiveCard(detailCard)" :title="detailCard.archived ? 'Kembalikan dari arsip' : 'Arsipkan kartu'" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                        {{ detailCard.archived ? 'Kembalikan' : 'Arsipkan' }}
                    </button>
                    <button type="button" @click="closeCard" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                </div>
            </div>

            <!-- Form lengkap (manager) — sama persis untuk buat & edit -->
            <form v-if="canManage" @submit.prevent="submitCard" class="grid grid-cols-2 gap-3 text-sm mb-2">
                <label class="col-span-2 block font-medium text-slate-600">Judul / Endorse
                    <input v-model="editForm.endorse" required :autofocus="creating" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
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
                        <option v-for="(v, k) in accounts" :key="k" :value="k">{{ v }}</option>
                    </select>
                </label>
                <label v-if="boardType === 'pipeline'" class="block font-medium text-slate-600">Jenis
                    <select v-model="editForm.jenis" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">— tanpa jenis —</option>
                        <option v-for="(v, k) in jenisList" :key="k" :value="k">{{ v }}</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600">Penanggung Jawab
                    <select v-model="editForm.assigned_to" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">— belum ditugaskan —</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </label>
                <label v-if="!isTodolist" class="block font-medium text-slate-600">Payment
                    <select v-model="editForm.payment_status" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="belum">Belum</option>
                        <option value="dp">DP</option>
                        <option value="lunas">Lunas</option>
                    </select>
                </label>
                <label v-if="!isTodolist" class="block font-medium text-slate-600">Jumlah IDR
                    <input type="number" step="0.01" v-model="editForm.amount_idr" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label v-if="!isTodolist" class="block font-medium text-slate-600">Jumlah USD
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
                <div class="col-span-2 flex justify-end gap-2">
                    <button v-if="creating" type="button" @click="closeCard" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="editForm.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">{{ creating ? 'Buat kartu' : 'Simpan perubahan' }}</button>
                </div>
            </form>

            <!-- Ringkasan read-only (non-manager) -->
            <div v-else-if="detailCard" class="space-y-2 text-sm mb-2">
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

            <!-- Lampiran — perlu id kartu, jadi baru muncul setelah kartunya ada -->
            <div v-if="detailCard" class="border-t border-slate-100 pt-4 mt-2">
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

            <!-- Komentar — idem: butuh id kartu -->
            <div v-if="detailCard" class="border-t border-slate-100 pt-4 mt-4">
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

<style scoped>
/* Placeholder kartu saat di-drag (ala Trello): kotak samar bergaris putus */
.drag-ghost {
    opacity: 0.5;
    border-style: dashed !important;
    background: rgb(240 253 244) !important; /* emerald-50 */
}
.drag-ghost > * { visibility: hidden; }
</style>

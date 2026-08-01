<script setup>
// Halaman Kanban (Vue) — kolom dinamis, drag-drop, label, + fitur kartu:
// deadline, arsip, deskripsi, attachment, komentar (staff yg ditugasi pun bisa komentar).
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'; // reaktivitas + sinkron scrollbar
import { router, useForm, usePage, Link } from '@inertiajs/vue3'; // Inertia: navigasi, form, props, Link
import Layout from '../Layout.vue'; // kerangka + sidebar
import ModalWrap from '../ModalWrap.vue'; // pembungkus modal
import draggable from 'vuedraggable'; // drag-drop kartu (SortableJS) ala Trello
import '../scripts/lib/charts'; // registrasi elemen Chart.js (dipakai bareng Dashboard/Pembukuan/OKR)
import { Bar } from 'vue-chartjs'; // chart batang siap pakai

// Props dari controller.
// baseUrl/pageTitle/showGallery membuat halaman ini dipakai dua modul:
// '/pipelines' (Sales Pipeline, tanpa galeri) & '/pipelines/kanban' (Kanban, pakai galeri).
const props = defineProps({
    category: String,
    counts: Object,
    categories: Object,
    board: Object,
    columns: Array,
    staff: Array,
    outputs: Array,
    canManage: Boolean,
    canManageStructure: Boolean,
    currentBoard: Object,
    monthlyScores: { type: Array, default: () => [] },
    scoreMonth: { type: String, default: '' },
    showArchived: Boolean,
    archivedCount: Number,
    accounts: Object,
    jenisList: Object,
    labels: { type: Array, default: () => [] }, // definisi label (dikelola owner)
    jenis: { type: Array, default: () => [] }, // chip jenis yang aktif (kosong = semua)
    jenisCounts: { type: Object, default: () => ({}) },
    dateFilters: { type: Object, default: () => ({}) }, // filter tanggal kartu dibuat
    boardTotal: { type: Number, default: 0 }, // estimasi nilai SELURUH board (tak ikut filter)
    baseUrl: { type: String, default: '/pipelines/kanban' },
    pageTitle: { type: String, default: 'Kanban' },
    showGallery: { type: Boolean, default: true },
    boardType: { type: String, default: 'kanban' }, // 'pipeline' (Sales) | 'kanban' — lihat isPipeline
    rate: { type: Number, default: 0 }, // kurs USD→IDR utk menjumlah nilai deal
    // ---- Kuartal (KPI) ----
    // `quarter.filtering` false = kuartal dipakai panel saja, kartu TIDAK disaring.
    quarter: { type: Object, default: () => ({ key: '', label: '', filtering: false }) },
    quarterOptions: { type: Array, default: () => [] },
    // null = peran ini tak berhak melihat capaian board (server tak mengirimnya
    // sama sekali, bukan sekadar disembunyikan di sini).
    quarterStats: { type: Object, default: null },
    boardCreator: { type: String, default: null }, // pembuat board (null = board lama)
    // Kanban saja (null di Sales Pipeline). Prop WAJIB dideklarasikan di sini:
    // prop yang tak dideklarasi jatuh ke $attrs & tak terbaca lewat `props`.
    columnTasks: { default: null }, // checklist delegasi per kolom (owner/manager → staff)
    objectives: { default: null },  // preview Objective OKR kuartal panel
});

// Palet warna label — HARUS cermin Label::COLORS (subset safelist di app.css).
// Warna di luar daftar ini tak ter-generate Tailwind di produksi.
const LABEL_COLORS = [
    'bg-red-500',
    'bg-amber-500',
    'bg-emerald-500',
    'bg-sky-500',
    'bg-purple-500',
    'bg-teal-500',
    'bg-indigo-500',
    'bg-rose-500',
    'bg-slate-500',
    'bg-slate-400',
    'bg-brand-600',
];
const LABEL_GROUP_NAMES = { 1: 'Status Pekerjaan', 2: 'Penanda Pekerjaan' };

const authUser = usePage().props.auth.user; // user login (izin hapus komentar)
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || ''; // token utk fetch
const todayStr = () => new Date().toISOString().slice(0, 10); // 'YYYY-MM-DD' hari ini
const isUrgent = (card) => (card.labels || []).some((l) => l.name === 'Urgent'); // kartu mendesak?
const groupForLabel = (label) => Number(label.group || (['Process', 'Belum', 'Selesai'].includes(label.name) ? 1 : 2));
// Tautan kontak. WA: buang non-digit, awalan 0 → 62 (format wa.me Indonesia).
// IG: buang '@' di depan kalau ada. Isian bebas dari user, jadi selalu dibersihkan.
const waLink = (v) => 'https://wa.me/' + String(v).replace(/\D/g, '').replace(/^0/, '62');
const igLink = (v) => 'https://instagram.com/' + String(v).trim().replace(/^@/, '');

// PATCH JSON + sinkron ulang bila server menolak.
// fetch() TIDAK reject pada 4xx/5xx — tanpa cek res.ok, kegagalan (403/422/500)
// lolos diam-diam & tampilan beda dgn DB sampai halaman di-reload.
const patchCard = async (url, body) => {
    try {
        const res = await fetch(url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(body),
        });
        if (!res.ok) {
            router.reload();
            return false;
        }
        return true;
    } catch {
        router.reload();
        return false;
    }
};
const fmtSize = (b) => (b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'); // ukuran file
const fmtCreated = (d) =>
    d ? new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

// Scrollbar kembar: yang atas membuat board bisa digeser tanpa turun ke dasar.
// Lebar batang atas mengikuti scrollWidth board dan keduanya saling sinkron.
const topScroll = ref(null);
const topScrollInner = ref(null);
const boardScroll = ref(null);
let scrollObserver;
const updateTopScrollWidth = () => {
    if (topScrollInner.value && boardScroll.value) topScrollInner.value.style.width = boardScroll.value.scrollWidth + 'px';
};
const syncScroll = (source, target) => {
    if (target) target.scrollLeft = source.scrollLeft;
};
onMounted(() => {
    nextTick(updateTopScrollWidth);
    scrollObserver = new ResizeObserver(updateTopScrollWidth);
    if (boardScroll.value) scrollObserver.observe(boardScroll.value);
});
onUnmounted(() => scrollObserver?.disconnect());

// State kartu per kolom (salinan board; di-resync bila board prop berubah).
// Deep-copy tiap array kolom → cols punya array sendiri, TIDAK berbagi referensi
// dgn prop Inertia. Wajib: SortableJS mutasi array ini via splice saat drag;
// kalau berbagi dgn prop readonly, mutasi gagal & kartu "balik" (drag seolah mati).
const cloneBoard = (b) => Object.fromEntries(Object.entries(b || {}).map(([k, v]) => [k, [...(v || [])]]));
const cols = ref(cloneBoard(props.board));
watch(
    () => props.board,
    (b) => {
        cols.value = cloneBoard(b);
    },
); // sinkron ulang saat Inertia kirim board baru

// ---- Statistik / KPI Anggota (agregat kartu yang tampil di board, per PJ) ----
// Client-side dari kartu board: tiap kartu sudah membawa assignee, completed_at,
// dan `ketepatan` (verdict tepat/terlambat/lewat dari server), jadi rumus telat
// TIDAK diduplikasi di sini. Cakupan = kartu yang sedang tampil, ikut filter
// aktif/arsip/jenis/kuartal — angkanya cocok dengan apa yang terlihat di board.
const statistikAnggota = computed(() => {
    const per = new Map(); // nama PJ → agregat
    for (const kartu of Object.values(cols.value).flat()) {
        if (kartu.is_kr_master) continue; // Abaikan kartu master OKR
        const nama = kartu.assignee || 'Belum ditugaskan';
        const s = per.get(nama) || { nama, total: 0, selesai: 0, telat: 0 };
        s.total += 1;
        const isProgressDone = String(kartu.progress || '').toLowerCase().startsWith('done');
        if (kartu.completed_at || kartu.done || isProgressDone) s.selesai += 1;
        if (kartu.ketepatan === 'lewat') s.telat += 1; // belum selesai & lewat deadline
        per.set(nama, s);
    }
    return (
        [...per.values()]
            .map((s) => ({ ...s, berjalan: s.total - s.selesai, skor: s.total ? Math.round((s.selesai / s.total) * 100) : 0 }))
            // "Belum ditugaskan" selalu di dasar; sisanya skor tertinggi dulu, seri → total terbanyak.
            .sort((a, b) => (a.nama === 'Belum ditugaskan') - (b.nama === 'Belum ditugaskan') || b.skor - a.skor || b.total - a.total)
    );
});

// Chart batang per anggota (Selesai/Berjalan/Telat) — warna sama dgn kolom tabel.
const barData = computed(() => ({
    labels: statistikAnggota.value.map((s) => s.nama),
    datasets: [
        { label: 'Selesai', data: statistikAnggota.value.map((s) => s.selesai), backgroundColor: '#10b981', borderRadius: 4 },
        { label: 'Berjalan', data: statistikAnggota.value.map((s) => s.berjalan), backgroundColor: '#f59e0b', borderRadius: 4 },
        { label: 'Telat', data: statistikAnggota.value.map((s) => s.telat), backgroundColor: '#ef4444', borderRadius: 4 },
    ],
}));
const barOpts = {
    responsive: true,
    maintainAspectRatio: false,
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } },
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
};

// Urutan kolom — salinan prop dgn alasan yang sama persis dgn `cols` di atas:
// SortableJS memutasi array ini via splice saat drag, dan prop Inertia readonly,
// jadi memakai props.columns langsung = drag kolom seolah mati.
// Shallow copy cukup: yang berubah hanya urutan array, isi objek kolomnya tidak.
const colOrder = ref([...props.columns]);
watch(
    () => props.columns,
    (c) => {
        colOrder.value = [...c];
    },
);

const colMenu = ref(null); // kolom yg menunya terbuka
const colNames = computed(() => Object.fromEntries(props.columns.map((c) => [c.key, c.name]))); // key→nama kolom
// Semua board Kanban adalah pengelolaan task ala Trello; field deal hanya untuk Sales Pipeline.
const isKanban = computed(() => props.boardType === 'kanban');

const cardCount = (key) => (cols.value[key] || []).length; // jml kartu per kolom
const dragDisabled = computed(() => !props.canManage || props.showArchived); // drag KARTU: staff boleh di board kanban
// drag KOLOM = struktur (columns.reorder) → cuma owner/manager/it/admin, bukan staff.
const colDragDisabled = computed(() => !props.canManageStructure || props.showArchived);

// ---- Nilai deal (ala Pipedrive: tiap stage punya total) ----
// Kartu bisa IDR, USD, atau dua-duanya → semua dijumlahkan dalam IDR pakai kurs.
// amount_* datang sbg string (cast decimal:2), jadi wajib Number().
const cardValue = (card) => Number(card.amount_idr || 0) + Number(card.amount_usd || 0) * props.rate;
// Total DP yang sudah dibayar (IDR). dp* datang sbg string (cast decimal:2).
const cardDpPaid = (card) => Number(card.dp1 || 0) + Number(card.dp2 || 0) + Number(card.dp3 || 0);
const colValue = (key) => (cols.value[key] || []).reduce((sum, c) => sum + cardValue(c), 0);
const boardValue = computed(() =>
    Object.values(cols.value)
        .flat()
        .reduce((sum, c) => sum + cardValue(c), 0),
);
const boardCount = computed(() => Object.values(cols.value).flat().length);

const rp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n)); // penuh: kartu
const rpShort = (n) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(n); // ringkas: header stage

// ---- Drag & drop (vuedraggable) — simpan isi & urutan kolom tujuan ----
// vuedraggable memutasi cols.value[key] langsung, lalu @change memicu:
//   'added'   di kolom penerima (kartu masuk dari kolom lain)
//   'moved'   di kolom yg sama  (kartu digeser naik/turun)
//   'removed' di kolom asal     — diabaikan, lihat di bawah
//
// Dulu cuma 'added' yang ditangani, jadi geseran naik/turun tak pernah
// tersimpan: di layar kartunya pindah, lalu balik ke tempat semula begitu
// halaman dimuat ulang.
//
// Yang dikirim bukan "kartu X ke kolom B", tapi seluruh isi kolom tujuan sesudah
// drag. Bentuk itu memuat kedua kejadian sekaligus & tak bisa setengah jadi.
// 'removed' diabaikan dgn sengaja: satu drag antar kolom memicu 'removed' di
// kolom asal DAN 'added' di kolom tujuan — menanganinya berarti dua kiriman
// untuk satu perbuatan, dan posisi kolom asal boleh berlubang (0,1,3,…) karena
// yang dipakai cuma urutan relatifnya.
const onCardChange = async (evt, toKey) => {
    if (!evt.added && !evt.moved) return;

    await patchCard('/pipelines/reorder', {
        progress: toKey,
        ids: (cols.value[toKey] || []).map((c) => c.id),
    });
};

// Urutan kolom sesudah drag. Cuma 'moved' yang mungkin terjadi: daftar kolom
// cuma satu di halaman ini & grupnya ('columns') tak dibagi dgn daftar lain,
// jadi tak ada kolom yang 'added' dari tempat lain.
// Dikirim SELURUH kolom board — server menolak kiriman sebagian (position kembar).
const onColumnChange = (evt) => {
    if (!evt.moved) return;

    patchCard('/columns/reorder', { ids: colOrder.value.map((c) => c.id) });
};

// ---- Checklist delegasi per kolom (owner/manager → staff) ----
// Kanban saja: props.columnTasks null di Sales Pipeline → tak dirender.
// Peta { <board_column_id>: [ {id,title,assigned_to,assignee,done}, ... ] }.
// Server sudah menyaring visibilitas (staff hanya item miliknya), jadi tiap
// item yang tampil PASTI boleh dicentang si penonton — tombol tak perlu dipagari
// di sini; controller tetap menegakkan otorisasi.
const colTasks = ref({ ...(props.columnTasks || {}) });
watch(() => props.columnTasks, (v) => { colTasks.value = { ...(v || {}) }; });

// Delegasi hanya ke staff yang ada.
// Penerima delegasi = SEMUA user (bukan cuma role 'staff'): tim ini isinya
// owner/manager/it, jadi membatasi ke 'staff' bikin dropdown kosong. Staff
// didahulukan di daftar supaya tetap jadi pilihan utama saat ada.
const ROLE_LABEL = { owner: 'Owner', manager: 'Manager', it: 'IT', admin: 'Admin', staff: 'Staff' };
const roleLabel = (r) => ROLE_LABEL[r] || r;
const assignableUsers = computed(() =>
    [...(props.staff || [])].sort((a, b) => (a.role === 'staff' ? -1 : 0) - (b.role === 'staff' ? -1 : 0)),
);
const initialsOf = (name) =>
    (name || '').split(' ').filter(Boolean).map((w) => w[0]).slice(0, 2).join('').toUpperCase();
const colDoneCount = (colId) => (colTasks.value[colId] || []).filter((t) => t.done).length;

const colTaskAddFor = ref(null); // id kolom yg form delegasinya sedang terbuka
const colTaskForm = useForm({ board_column_id: null, title: '', assigned_to: '' });
const openColTaskAdd = (col) => {
    colTaskForm.reset();
    colTaskForm.board_column_id = col.id;
    colTaskAddFor.value = col.id;
};
const submitColTask = () => {
    if (!colTaskForm.title.trim() || !colTaskForm.assigned_to) return;
    colTaskForm.post('/column-tasks', {
        preserveScroll: true, preserveState: true, only: ['columnTasks'],
        onSuccess: () => { colTaskForm.reset(); colTaskAddFor.value = null; },
    });
};
const toggleColTask = (t) => {
    t.done = !t.done; // optimistik; reload berikutnya = kebenaran server
    router.patch(`/column-tasks/${t.id}/toggle`, {}, { preserveScroll: true, preserveState: true, only: ['columnTasks'] });
};
const deleteColTask = (t) =>
    router.delete(`/column-tasks/${t.id}`, { preserveScroll: true, preserveState: true, only: ['columnTasks'] });

// Objective dianggap selesai bila progress-nya ≥ 100 (cermin isObjectiveComplete
// di halaman OKR untuk cabang progress). Dipakai preview Objective di atas kolom.
const objDone = (o) => o.progress !== null && o.progress >= 100;

// ---- Modal kartu: dipakai untuk BUAT dan EDIT sekaligus ----
// Dulu ada dua modal terpisah — form tambah cuma subset form edit, jadi kartu baru
// harus dibuat dulu lalu dibuka lagi untuk mengisi detailnya. Sekarang satu modal:
// `creating` true = kartu baru (POST), selain itu = edit kartu `detailId` (PUT).
const detailId = ref(null); // id kartu yang dibuka (null saat membuat)
const creating = ref(false); // sedang membuat kartu baru?
const detailCard = computed(() =>
    detailId.value
        ? Object.values(cols.value)
              .flat()
              .find((c) => c.id === detailId.value)
        : null,
);
// `progressKey` (bukan `progress`) — hindari bentrok properti bawaan useForm
// (`form.progress` = progres upload). Dipetakan ke `progress` saat submit.
const editForm = useForm({
    category: props.category,
    endorse: '',
    jenis: '',
    description: '',
    account: 'fk',
    progressKey: 'script',
    assigned_to: '',
    payment_status: 'belum',
    amount_idr: '',
    amount_usd: '',
    dp1: '',
    dp2: '',
    dp3: '',
    link: '',
    deadline: '',
    score: '',
    outputs: [],
    notes: '',
    labels: [],
    kontak_wa: '',
    kontak_gmail: '',
    kontak_ig: '',
    revisi: 0,
    newAttachment: null,
});

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
    // Todo baru default ikut penanggung jawab kartunya (sesuai kolom) — user tetap bisa ganti.
    taskForm.assigned_to = card.assigned_to ?? '';
    editForm.payment_status = card.payment_status ?? 'belum';
    editForm.amount_idr = card.amount_idr ?? '';
    editForm.amount_usd = card.amount_usd ?? '';
    editForm.dp1 = card.dp1 ?? '';
    editForm.dp2 = card.dp2 ?? '';
    editForm.dp3 = card.dp3 ?? '';
    editForm.link = card.link ?? '';
    editForm.deadline = card.deadline ?? '';
    editForm.score = card.score ?? '';
    editForm.revisi = card.revisi ?? 0;
    editForm.outputs = Array.isArray(card.output_ids) ? card.output_ids.map(Number) : [];
    editForm.notes = card.notes ?? '';
    editForm.labels = Array.isArray(card.labels) ? card.labels.map((l) => ({ ...l, group: groupForLabel(l) })) : [];
    editForm.kontak_wa = card.kontak_wa ?? '';
    editForm.kontak_gmail = card.kontak_gmail ?? '';
    editForm.kontak_ig = card.kontak_ig ?? '';
    editForm.newAttachment = null;
};

const openAdd = (progress) => {
    if (!props.canManage) return;
    fillForm({ progress }); // kosong, kecuali kolom tujuan
    detailId.value = null;
    creating.value = true;
};
const openDetail = (card) => {
    creating.value = false;
    detailId.value = card.id;
    if (props.canManage) fillForm(card);
};

// Tautan reminder membawa ?card=ID. Begitu board selesai dimuat, buka kartu
// tersebut langsung supaya klik lonceng tidak berhenti di halaman board saja.
onMounted(() => {
    const cardId = Number(new URLSearchParams(window.location.search).get('card'));
    const card = cardId
        ? Object.values(cols.value)
              .flat()
              .find((item) => item.id === cardId)
        : null;
    if (card) openDetail(card);
});

const closeCard = () => {
    detailId.value = null;
    creating.value = false;
    editForm.newAttachment = null;
    attachForm.reset('file');
};

// Textarea Deskripsi/Notes mengikuti tinggi isi sampai 320px; setelah itu
// scrollbar internal menjaga modal tidak tumbuh melewati layar.
const resizeTextarea = (el) => {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 320) + 'px';
};
const vAutoResize = {
    mounted: resizeTextarea,
    updated: resizeTextarea,
};

// Tutup modal setelah simpan sukses (samakan dgn arsip/hapus & modal Order).
// Gagal validasi → modal TETAP terbuka supaya form.errors kelihatan.
const submitCard = () => {
    const form = editForm.transform(({ progressKey, score, ...rest }) => ({
        ...rest,
        progress: progressKey,
        ...(isOwner.value && isKanban.value ? { score } : {}),
        // Board task tidak boleh membawa data deal tersembunyi dari form Sales.
        ...(isKanban.value
            ? {
                  jenis: '',
                  account: 'fk',
                  payment_status: 'belum',
                  amount_idr: '',
                  amount_usd: '',
                  dp1: '',
                  dp2: '',
                  dp3: '',
                  outputs: [],
                  kontak_wa: '',
                  kontak_gmail: '',
                  kontak_ig: '',
              }
            : {}),
    }));
    if (creating.value) {
        form.post('/pipelines', { preserveScroll: true, forceFormData: true, onSuccess: closeCard });
    } else {
        form.put('/pipelines/' + detailId.value, { preserveScroll: true, onSuccess: closeCard });
    }
};
// Dua kelompok kategori, masing-masing pilih maksimal satu. Snapshot tetap
// disimpan di JSON kartu agar label lama tidak rusak saat definisi diubah.
const labelGroups = computed(() => ({
    1: props.labels.filter((label) => groupForLabel(label) === 1),
    2: props.labels.filter((label) => groupForLabel(label) === 2),
}));
const hasLabel = (nama) => editForm.labels.some((l) => l.name === nama);
const toggleLabel = (lp) => {
    const group = groupForLabel(lp);
    const otherGroup = editForm.labels.filter((label) => groupForLabel(label) !== group);
    editForm.labels = hasLabel(lp.name) ? otherGroup : [...otherGroup, { name: lp.name, group, color: lp.color }];
};
const toggleOutput = (id) => {
    editForm.outputs = editForm.outputs.includes(id) ? editForm.outputs.filter((x) => x !== id) : [...editForm.outputs, id];
};

// ---- Arsip / hapus kartu ----
const archiveCard = (card) => {
    if (props.canManage)
        router.patch(`/pipelines/${card.id}/archive`, {}, { preserveScroll: true, onSuccess: () => (detailId.value = null) });
};
// Tandai kartu selesai / batal. completed_at terisi = sudah selesai → kirim kebalikannya.
const toggleDone = (card) => {
    if (props.canManage) router.patch(`/pipelines/${card.id}/done`, { done: !card.completed_at }, { preserveScroll: true });
};
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

// Tugas terdelegasi di dalam card utama KR.
const taskForm = useForm({ title: '', assigned_to: '', deadline: '' });
const addTask = () =>
    taskForm.post(`/pipelines/${detailId.value}/tasks`, {
        preserveScroll: true,
        onSuccess: () => taskForm.reset('title', 'deadline'),
    });
const toggleTask = (task) => router.patch(`/pipeline-tasks/${task.id}`, { done: !task.done }, { preserveScroll: true });
const deleteTask = (task) => router.delete(`/pipeline-tasks/${task.id}`, { preserveScroll: true });

// ---- Lampiran (pilih file atau paste screenshot dari clipboard) ----
const attachForm = useForm({ file: null });
const submitAttach = () => {
    if (!attachForm.file) return;
    attachForm.post(`/pipelines/${detailId.value}/attachments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => attachForm.reset('file'),
    });
};
const deleteAttachment = (id) => router.delete(`/attachments/${id}`, { preserveScroll: true });

// Screenshot dari clipboard dapat langsung masuk ke upload lampiran lewat
// Ctrl+V (Windows/Linux) atau Cmd+V (Mac). Paste teks biasa tidak disentuh:
// event hanya dicegah bila clipboard benar-benar membawa sebuah image.
const pasteScreenshot = (event) => {
    if ((!creating.value && !detailCard.value) || !props.canManageStructure) return;
    const item = [...(event.clipboardData?.items || [])].find((entry) => entry.type.startsWith('image/'));
    const blob = item?.getAsFile();
    if (!blob) return;

    const extension = blob.type.split('/')[1]?.replace('jpeg', 'jpg') || 'png';
    const stamp = new Date().toISOString().replace(/[:.]/g, '-');
    const file = new File([blob], `screenshot-${stamp}.${extension}`, {
        type: blob.type,
        lastModified: Date.now(),
    });

    if (creating.value) editForm.newAttachment = file;
    else attachForm.file = file;
    event.preventDefault();
};
onMounted(() => window.addEventListener('paste', pasteScreenshot));
onUnmounted(() => window.removeEventListener('paste', pasteScreenshot));

// ---- Kelola kategori (OWNER only) — internalnya tetap tabel `labels` ----
const isOwner = computed(() => authUser?.role === 'owner');
const labelManageOpen = ref(false);
const labelForm = useForm({ name: '', group: 1, color: LABEL_COLORS[0] }); // form tambah
const labelEditId = ref(null); // id label yg sedang diedit
const labelEditForm = useForm({ name: '', group: 1, color: LABEL_COLORS[0] }); // form edit inline
const addLabel = () => {
    if (!labelForm.name.trim()) return;
    labelForm.post('/labels', { preserveScroll: true, onSuccess: () => labelForm.reset() });
};
const startEditLabel = (l) => {
    labelEditId.value = l.id;
    labelEditForm.name = l.name;
    labelEditForm.group = groupForLabel(l);
    labelEditForm.color = l.color;
};
const saveEditLabel = () =>
    labelEditForm.put(`/labels/${labelEditId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            labelEditId.value = null;
        },
    });
const deleteLabel = (id) => {
    if (!confirm('Hapus kategori ini? Kartu yang sudah memakainya tidak berubah.')) return;
    router.delete(`/labels/${id}`, { preserveScroll: true });
};

// ---- Modal Diagram Organisasi (Sesuai Diagram User) ----
const showOrgDiagram = ref(false);

// ---- Modal board & kolom ----
const boardCreateOpen = ref(false);
const boardEditOpen = ref(false);
const colCreateOpen = ref(false);
const colEditOpen = ref(false);
const colEditId = ref(null);
// Tanpa `type`: board baru selalu kanban (ditegakkan BoardController). Board pipeline
// cuma `sales` & tak bisa ditambah — pembeda deal di sana adalah `jenis`.
const boardForm = useForm({ name: '' });
const colForm = useForm({ board_key: props.category, name: '' });
const submitBoardCreate = () => boardForm.post('/boards', { onSuccess: () => (boardCreateOpen.value = false) });
const submitBoardEdit = () => boardForm.put('/boards/' + props.currentBoard.key, { onSuccess: () => (boardEditOpen.value = false) });
const submitColCreate = () => colForm.post('/columns', { onSuccess: () => (colCreateOpen.value = false) });
const submitColEdit = () => colForm.put('/columns/' + colEditId.value, { onSuccess: () => (colEditOpen.value = false) });
const openColEdit = (id, name) => {
    colEditId.value = id;
    colForm.name = name;
    colEditOpen.value = true;
};
const deleteColumn = (id) => {
    if (props.canManageStructure && confirm('Hapus kolom ini? (hanya bila kosong)')) router.delete('/columns/' + id);
};
const deleteBoard = () => {
    if (confirm(`Hapus board "${props.currentBoard.name}"? (hanya bila kosong)`)) router.delete('/boards/' + props.currentBoard.key);
};
// Pindah board sengaja MELEPAS chip jenis & tampilan arsip (tak lewat
// paramsFilter): keduanya milik board yang ditinggalkan. Filter tanggal &
// kuartal ikut terbawa — itu rentang waktu yang sedang ditinjau, dan orang
// biasanya membandingkan periode yang sama antar board.
const switchBoard = (e) =>
    router.get(
        props.baseUrl,
        {
            category: e.target.value,
            created_from: createdFrom.value || undefined,
            created_to: createdTo.value || undefined,
            q: quarterPilih.value || undefined,
        },
        { preserveState: false },
    );

// Sales cuma punya SATU board (`sales`): tak ada pilih/buat/ubah/hapus board.
const isPipeline = computed(() => props.boardType === 'pipeline');

// ---- Filter Date Marker (created_at) — berlaku di Sales dan semua Kanban ----
const createdFrom = ref(props.dateFilters.created_from || '');
const createdTo = ref(props.dateFilters.created_to || '');
const dateFilterAktif = computed(() => Boolean(createdFrom.value || createdTo.value));
const applyDateFilter = () => router.get(props.baseUrl, paramsFilter(), { preserveState: false });
const resetDateFilter = () => {
    createdFrom.value = '';
    createdTo.value = '';
    applyDateFilter();
};

// ---- Filter kuartal (KPI) ----
// Dasarnya DEADLINE kartu, bukan tanggal dibuat — Date Marker di atas sudah
// memakai created_at, dan dua filter ini memang menjawab pertanyaan berbeda:
// "kapan kartu ini masuk" vs "kapan kartu ini harus selesai".
//
// Nilainya HANYA terisi saat kuartal benar-benar menyaring. Panel target selalu
// menampilkan sebuah kuartal (yang berjalan, bila belum dipilih), jadi memakai
// props.quarter.key sbg nilai ref akan membuat setiap kunjungan biasa mengirim
// ?q dan diam-diam menyaring kartu tanpa pernah diminta.
const quarterPilih = ref(props.quarter.filtering ? props.quarter.key : '');

// Semua navigasi di halaman ini WAJIB lewat sini. Ada lima tombol yang memuat
// ulang halaman (pindah board, chip jenis, Date Marker, arsip, kuartal) — kalau
// masing-masing menyusun querystring sendiri, satu saja yang lupa membawa `q`
// sudah cukup membuat filter kuartal hilang saat tombol lain ditekan.
const paramsFilter = (override = {}) => ({
    category: props.category,
    jenis: props.jenis?.length ? props.jenis : undefined,
    created_from: createdFrom.value || undefined,
    created_to: createdTo.value || undefined,
    q: quarterPilih.value || undefined,
    archived: props.showArchived ? 1 : undefined,
    ...override,
});

const applyQuarter = (key) => {
    quarterPilih.value = key;
    router.get(props.baseUrl, paramsFilter(), { preserveState: false });
};

// Rasio tepat waktu board pada kuartal panel. null = belum ada kartu selesai
// yang punya deadline, jadi belum ada yang bisa dinilai.
const persenTepat = computed(() => props.quarterStats?.ketepatan?.persen_tepat ?? null);

// Tampilan badge ketepatan di kartu. Nilainya dari server (Pipeline::ketepatan()),
// TIDAK dihitung ulang di sini: kalau dihitung dua kali dgn dua rumus, kartu bisa
// menampilkan 'tepat' sementara rekap di panel menghitungnya 'terlambat'.
// Kunci yang tak dikenal (termasuk null) → tak ada badge sama sekali.
const KETEPATAN = {
    tepat: { label: 'Tepat waktu', cls: 'bg-emerald-100 text-emerald-700' },
    terlambat: { label: 'Terlambat', cls: 'bg-red-100 text-red-700' },
    lewat: { label: 'Lewat deadline', cls: 'bg-amber-100 text-amber-800' },
};

// ---- Filter jenis (chip, board sales) ----
// WAJIB chip, JANGAN dropdown. Versi dropdown pernah ada & dibuang: letaknya sama
// dgn dropdown board yang lama, jadi memilih jenis terbaca sbg "pindah board".
// Chip tak punya masalah itu — bisa aktif banyak sekaligus, & "Semua" selalu terlihat.
const jenisAktif = computed(() => new Set(props.jenis || []));
const filterAktif = computed(() => jenisAktif.value.size > 0);
// Kuartal ikut dihitung: tanpa ini, teks "N kartu aktif di board ini" tetap
// muncul saat kuartal menyaring & angkanya terbaca seolah board menyusut.
const anyFilterAktif = computed(() => filterAktif.value || dateFilterAktif.value || Boolean(quarterPilih.value));

// Kirim ulang halaman dgn daftar chip yang baru. Array kosong → param dibuang
// (?jenis[]= kosong tetap terbaca array berisi '' oleh Laravel).
const pergiKeFilter = (keys) =>
    router.get(
        props.baseUrl,
        paramsFilter({
            jenis: keys.length ? keys : undefined,
        }),
        { preserveState: false },
    );

const toggleJenis = (key) => {
    const next = new Set(jenisAktif.value);
    next.has(key) ? next.delete(key) : next.add(key);
    pergiKeFilter([...next]);
};
const resetJenis = () => filterAktif.value && pergiKeFilter([]);

// `jenis` ikut dibawa supaya filter tak hilang saat pindah ke arsip & sebaliknya.
const toggleArchiveView = () =>
    router.get(
        props.baseUrl,
        paramsFilter({
            archived: props.showArchived ? undefined : 1,
        }),
        { preserveState: false },
    );
</script>

<template>
    <Layout :title="pageTitle">
        <div class="p-6">
            <!-- Toolbar board. Di Sales tak dirender sama sekali: isinya tinggal tombol
                 Arsip (board tunggal → tak ada dropdown/aksi board, angka total dibuang),
                 dan panel putih berisi satu tombol cuma jadi kotak menganga. Arsipnya
                 pindah ke baris Filter di bawah. -->
            <div
                v-if="!isPipeline"
                class="bg-white border border-brand-100 rounded-2xl shadow-sm p-4 mb-3 flex flex-wrap items-center gap-3"
            >
                <!-- Balik ke galeri (kanban luar; Sales Pipeline tak punya galeri) -->
                <Link
                    v-if="showGallery"
                    :href="baseUrl"
                    title="Semua board"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-brand-700 mt-5 pr-2 border-r border-slate-200"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Galeri
                </Link>
                <!-- Board: Kanban punya banyak, jadi tetap bisa dipilih -->
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Board</p>
                    <select
                        :value="category"
                        class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-brand-400 outline-none"
                        @change="switchBoard"
                    >
                        <option v-for="(cv, ck) in categories" :key="ck" :value="ck">{{ cv }} · {{ counts[ck] ?? 0 }}</option>
                    </select>
                </div>
                <!-- Ringkasan board: jml + total nilai -->
                <span class="text-sm text-slate-400 mt-5">
                    {{ boardCount }} {{ isKanban ? 'task' : 'deal'
                    }}<template v-if="!isKanban">
                        · <span class="font-semibold text-slate-600">{{ rp(boardValue) }}</span></template
                    >
                </span>

                <!-- Aksi board (manager, mode aktif) -->
                <div v-if="canManageStructure && !showArchived" class="flex items-center gap-1.5 mt-5">
                    <button
                        class="inline-flex items-center gap-1 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition"
                        @click="
                            boardForm.name = '';
                            boardCreateOpen = true;
                        "
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Board
                    </button>
                    <template v-if="currentBoard">
                        <button
                            title="Ubah nama board"
                            class="p-2 rounded-lg text-slate-400 hover:bg-brand-50 hover:text-brand-600 transition"
                            @click="
                                boardForm.name = currentBoard.name;
                                boardEditOpen = true;
                            "
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z"
                                />
                            </svg>
                        </button>
                        <button
                            v-if="currentBoard.key !== 'todolist'"
                            title="Hapus board"
                            class="p-2 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 transition"
                            @click="deleteBoard"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </template>
                </div>

                <!-- Toggle lihat arsip -->
                <button
                    :class="[
                        'ml-auto mt-5 inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-3 py-1.5 border transition',
                        showArchived
                            ? 'bg-brand-600 text-white border-brand-600'
                            : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50',
                    ]"
                    @click="toggleArchiveView"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4"
                        />
                    </svg>
                    {{ showArchived ? 'Lihat aktif' : `Arsip (${archivedCount})` }}
                </button>

                <!-- Badge view-only -->
                <span
                    v-if="!canManage"
                    class="mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 bg-slate-100 border border-slate-200 rounded-full px-3 py-1.5"
                    title="Anda hanya bisa melihat & komentar"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5 5 12 5s9.5 7 9.5 7-2.5 7-9.5 7-9.5-7-9.5-7z" />
                    </svg>
                    Lihat & komentar
                </span>
            </div>

            <!-- Filter jenis (Sales). Sengaja BARIS SENDIRI di luar toolbar & berbentuk
                 chip: versi dropdown di dalam toolbar terbaca sbg penukar board. -->
            <div v-if="isPipeline" class="flex items-center gap-2 flex-wrap mb-3 px-0.5">
                <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-widest text-slate-400 font-semibold">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
                    </svg>
                    Filter
                </span>
                <!-- "Semua" = jalan pulang, selalu terlihat -->
                <button
                    type="button"
                    :aria-pressed="!filterAktif"
                    :class="[
                        'text-xs font-semibold rounded-full px-3 py-1.5 border transition',
                        !filterAktif
                            ? 'bg-slate-800 text-white border-slate-800'
                            : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400',
                    ]"
                    @click="resetJenis"
                >
                    Semua
                </button>
                <button
                    v-for="(label, key) in jenisList"
                    :key="key"
                    type="button"
                    :aria-pressed="jenisAktif.has(key)"
                    :class="[
                        'inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-3 py-1.5 border transition',
                        jenisAktif.has(key)
                            ? 'bg-brand-50 text-brand-700 border-brand-500'
                            : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400',
                    ]"
                    @click="toggleJenis(key)"
                >
                    {{ label }}
                    <span :class="['text-[10px] font-mono', jenisAktif.has(key) ? 'text-brand-600' : 'text-slate-400']">{{
                        jenisCounts[key] ?? 0
                    }}</span>
                </button>

                <!-- Estimasi SELURUH board — pakai prop boardTotal dari server, BUKAN
                     boardValue: yang kedua menjumlah kartu yang tampil, jadi menyusut
                     begitu chip dipilih. Nilai tersaring ditaruh terpisah di bawahnya. -->
                <span class="ml-auto text-xs text-slate-400 whitespace-nowrap">
                    Estimasi board <span class="font-bold text-slate-600 text-sm">{{ rp(boardTotal) }}</span>
                </span>

                <!-- Arsip menumpang baris ini karena toolbar Sales sudah tak dirender.
                     WAJIB tetap ada di suatu tempat: ini satu-satunya jalan melihat &
                     mengembalikan kartu terarsip, sementara tombol "Arsipkan" di modal
                     kartu masih hidup. -->
                <button
                    :class="[
                        'inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-3 py-1.5 border transition',
                        showArchived
                            ? 'bg-brand-600 text-white border-brand-600'
                            : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50',
                    ]"
                    @click="toggleArchiveView"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4"
                        />
                    </svg>
                    {{ showArchived ? 'Lihat aktif' : `Arsip (${archivedCount})` }}
                </button>
                <!-- Badge view-only: Sales tak punya toolbar, jadi ikut di sini -->
                <span
                    v-if="!canManage"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 bg-slate-100 border border-slate-200 rounded-full px-3 py-1.5"
                    title="Anda hanya bisa melihat & komentar"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5 5 12 5s9.5 7 9.5 7-2.5 7-9.5 7-9.5-7-9.5-7z" />
                    </svg>
                    Lihat & komentar
                </span>
            </div>

            <!-- Filter Date Marker = tanggal kartu dibuat. Berlaku sama untuk Sales
                 dan Kanban; isi salah satu batas saja juga valid. -->
            <div class="flex flex-wrap items-end gap-2 mb-3 px-0.5">
                <span class="pb-2 text-[10px] uppercase tracking-widest text-slate-400 font-semibold">Date Marker</span>
                <label class="text-[10px] text-slate-500"
                    >Dari
                    <input
                        v-model="createdFrom"
                        type="date"
                        class="mt-0.5 block bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs outline-none focus:ring-2 focus:ring-brand-400"
                        @change="applyDateFilter"
                    />
                </label>
                <label class="text-[10px] text-slate-500"
                    >Sampai
                    <input
                        v-model="createdTo"
                        type="date"
                        class="mt-0.5 block bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs outline-none focus:ring-2 focus:ring-brand-400"
                        @change="applyDateFilter"
                    />
                </label>
                <button
                    v-if="dateFilterAktif"
                    class="mb-0.5 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:text-brand-700"
                    @click="resetDateFilter"
                >
                    Reset tanggal
                </button>
            </div>

            <!-- ================= Panel kuartal (KPI board) =================
                 Selalu tampil, walau kuartalnya belum dipilih: halaman butuh
                 satu angka acuan tetap. Yang berubah cuma apakah kartunya ikut
                 disaring — ditandai chip "menyaring kartu" di bawah. -->
            <div class="rounded-2xl border border-brand-100 bg-white shadow-sm px-4 py-3 mb-3">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold">Kuartal</span>

                    <!-- Pemilih kuartal. Dropdown (bukan chip) disengaja: daftarnya
                         panjang & saling meniadakan — beda dgn chip jenis yang bisa
                         aktif banyak sekaligus. Letaknya pun jauh dari dropdown board
                         di toolbar atas, jadi tak terbaca sbg "pindah board". -->
                    <select
                        :value="quarterPilih"
                        class="bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs outline-none focus:ring-2 focus:ring-brand-400"
                        @change="applyQuarter($event.target.value)"
                    >
                        <option value="">Semua kuartal</option>
                        <option v-for="o in quarterOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                    </select>

                    <span
                        v-if="quarter.filtering"
                        class="inline-flex items-center gap-1 text-[10px] font-semibold text-brand-700 bg-brand-50 border border-brand-200 rounded-full px-2 py-0.5"
                    >
                        menyaring kartu berdasarkan deadline
                    </span>
                    <span v-else class="text-[10px] text-slate-400">panel menampilkan {{ quarter.label }} · kartu tidak disaring</span>

                    <!-- Pembuat board: informasi netral, bukan penilaian kinerja —
                         sengaja di LUAR blok tergating supaya tetap terlihat semua peran. -->
                    <span
v-if="boardCreator && !quarterStats"
class="ml-auto text-[11px] text-slate-400"
                        >Board dibuat oleh <b class="text-slate-500">{{ boardCreator }}</b></span
                    >

                    <!-- Capaian target: angka + bar. Target belum ditetapkan → tak ada
                         persen sama sekali (bukan 0%), lihat catatan di controller. -->
                    <div v-if="quarterStats" class="flex items-center gap-2 ml-auto">
                        <span class="text-xs text-slate-500">
                            Target {{ quarter.label }}:
                            <b class="text-slate-700">{{ quarterStats.done }}/{{ quarterStats.target > 0 ? quarterStats.target : '—' }}</b>
                            <span class="text-slate-400"> kartu selesai</span>
                        </span>
                        <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                :class="[
                                    'h-full rounded-full transition-all',
                                    quarterStats.percent === null
                                        ? 'bg-slate-300'
                                        : quarterStats.percent >= 100
                                          ? 'bg-emerald-500'
                                          : quarterStats.percent >= 60
                                            ? 'bg-amber-500'
                                            : 'bg-red-500',
                                ]"
                                :style="{ width: Math.min(100, Math.max(0, quarterStats.percent || 0)) + '%' }"
                            ></div>
                        </div>
                        <span class="text-xs font-bold text-slate-600 w-12 text-right">{{
                            quarterStats.percent === null ? '—' : quarterStats.percent + '%'
                        }}</span>
                    </div>
                </div>

                <!-- Analitik ketepatan: pertanyaan "pekerjaan di board ini sering
                     telat atau tepat waktu". 'Lewat deadline' dipisah dari
                     'terlambat' karena belum final — masih bisa diselamatkan.
                     Seluruh baris ini penilaian kinerja → hanya peran pengelola. -->
                <div v-if="quarterStats" class="flex flex-wrap items-center gap-2 mt-2.5 pt-2.5 border-t border-slate-100">
                    <span
                        class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2.5 py-1"
                    >
                        Tepat waktu <b>{{ quarterStats.ketepatan.tepat ?? 0 }}</b>
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-red-700 bg-red-50 border border-red-200 rounded-full px-2.5 py-1"
                    >
                        Terlambat <b>{{ quarterStats.ketepatan.terlambat ?? 0 }}</b>
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2.5 py-1"
                    >
                        Lewat deadline <b>{{ quarterStats.ketepatan.lewat ?? 0 }}</b>
                    </span>
                    <span class="text-[11px] text-slate-500">
                        Rasio tepat waktu:
                        <b :class="persenTepat === null ? 'text-slate-400' : persenTepat >= 80 ? 'text-emerald-600' : 'text-red-600'">
                            {{ persenTepat === null ? 'belum bisa dinilai' : persenTepat + '%' }}
                        </b>
                    </span>
                    <!-- Kartu tanpa deadline tak masuk kuartal mana pun. Disebut
                         terang-terangan supaya filter kuartal tak terlihat seperti
                         menghilangkan kartu. -->
                    <span
                        v-if="quarterStats.no_deadline > 0"
                        class="text-[11px] text-slate-400"
                        :title="'Kartu tanpa deadline tidak masuk kuartal mana pun'"
                    >
                        · {{ quarterStats.no_deadline }} kartu tanpa deadline (di luar hitungan)
                    </span>
                    <span
v-if="boardCreator"
class="ml-auto text-[11px] text-slate-400"
                        >Board dibuat oleh <b class="text-slate-500">{{ boardCreator }}</b></span
                    >
                </div>
            </div>

            <!-- Statistik anggota di atas board agar kondisi tim langsung terlihat
                 sebelum pengguna masuk ke daftar kartu. -->
            <div
                v-if="isKanban && canManage && statistikAnggota.length"
                class="bg-white border border-slate-200 rounded-2xl shadow-sm mb-3 overflow-hidden"
            >
                <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-100">
                    <svg
                        class="w-4 h-4 text-slate-500 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l3-4 3 3 5-7" />
                    </svg>
                    <span class="font-bold text-sm text-slate-700">Statistik / KPI Anggota</span>
                    <span class="hidden sm:inline text-[11px] text-slate-400 ml-auto"
                        >Target minimum score 100 per anggota · {{ scoreMonth }}</span
                    >
                </div>
                <div v-if="monthlyScores.length" class="flex gap-2 overflow-x-auto px-4 py-3 border-b border-slate-100 bg-slate-50/60">
                    <div
                        v-for="anggota in monthlyScores"
                        :key="anggota.id"
                        class="min-w-36 rounded-xl border border-slate-200 bg-white px-3 py-2"
                    >
                        <p class="text-xs font-semibold text-slate-700 truncate">{{ anggota.name }}</p>
                        <p :class="['text-lg font-bold tabular-nums', anggota.shortage ? 'text-amber-600' : 'text-emerald-600']">
                            {{ anggota.score }} <span class="text-[10px] font-medium text-slate-400">/ min. 100</span>
                        </p>
                        <p :class="['text-[10px]', anggota.shortage ? 'text-amber-600' : 'text-emerald-600']">
                            {{ anggota.shortage ? `Kurang ${anggota.shortage} poin` : 'Target tercapai' }}
                        </p>
                    </div>
                </div>
                <div class="grid lg:grid-cols-2 items-start">
                    <div class="overflow-x-auto lg:border-r border-slate-100">
                        <table class="w-full text-sm min-w-[480px]">
                            <thead>
                                <tr
                                    class="text-left text-[10px] uppercase tracking-widest text-slate-400 bg-slate-50 border-b border-slate-100"
                                >
                                    <th class="py-2.5 px-4 font-semibold">Anggota</th>
                                    <th class="py-2.5 px-4 font-semibold text-center w-16">Total</th>
                                    <th class="py-2.5 px-4 font-semibold text-center w-16">Selesai</th>
                                    <th class="py-2.5 px-4 font-semibold text-center w-16">Berjalan</th>
                                    <th class="py-2.5 px-4 font-semibold text-center w-16">Telat</th>
                                    <th class="py-2.5 px-4 font-semibold w-48">Skor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="s in statistikAnggota"
                                    :key="s.nama"
                                    class="border-b border-slate-50 last:border-b-0 hover:bg-slate-50/60"
                                >
                                    <td class="py-2.5 px-4 font-semibold text-slate-700">{{ s.nama }}</td>
                                    <td class="py-2.5 px-4 text-center tabular-nums text-slate-600">{{ s.total }}</td>
                                    <td class="py-2.5 px-4 text-center tabular-nums font-semibold text-emerald-600">{{ s.selesai }}</td>
                                    <td :class="['py-2.5 px-4 text-center tabular-nums', s.berjalan ? 'text-amber-600' : 'text-slate-300']">
                                        {{ s.berjalan }}
                                    </td>
                                    <td
                                        :class="[
                                            'py-2.5 px-4 text-center tabular-nums',
                                            s.telat ? 'font-bold text-red-600' : 'text-slate-300',
                                        ]"
                                    >
                                        {{ s.telat }}
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    :class="[
                                                        'h-full rounded-full transition-all',
                                                        s.skor >= 100
                                                            ? 'bg-emerald-500'
                                                            : s.skor >= 60
                                                              ? 'bg-amber-500'
                                                              : s.skor > 0
                                                                ? 'bg-red-500'
                                                                : 'bg-slate-200',
                                                    ]"
                                                    :style="{ width: s.skor + '%' }"
                                                ></div>
                                            </div>
                                            <span class="text-xs font-bold text-slate-600 w-10 text-right">{{ s.skor }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">
                        <div class="h-64"><Bar :data="barData" :options="barOpts" /></div>
                    </div>
                </div>
            </div>

            <!-- Pembeda mode: Task Aktif (hijau) vs Mode Arsip (amber) -->
            <div
                :class="[
                    'flex items-center gap-2.5 rounded-xl border px-4 py-2.5 mb-5',
                    showArchived ? 'bg-amber-50 border-amber-300' : 'bg-emerald-50 border-emerald-200',
                ]"
            >
                <!-- ikon: kotak arsip / papan aktif -->
                <svg
                    v-if="showArchived"
                    class="w-4 h-4 text-amber-600 flex-shrink-0"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4"
                    />
                </svg>
                <svg
                    v-else
                    class="w-4 h-4 text-emerald-600 flex-shrink-0"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10M4 18h10" />
                </svg>
                <span :class="['font-bold text-sm', showArchived ? 'text-amber-800' : 'text-emerald-800']">{{
                    showArchived ? 'Mode Arsip' : 'Task Aktif'
                }}</span>
                <!-- Saat filter aktif: boardCount, BUKAN counts[category]. Yang kedua tak ikut
                     filter, jadi angkanya beda dgn kartu yang benar-benar tampil. -->
                <span :class="['text-xs', showArchived ? 'text-amber-700' : 'text-emerald-700']">{{
                    showArchived
                        ? `${archivedCount} kartu terarsip · buka kartu untuk mengembalikan`
                        : anyFilterAktif
                          ? `${boardCount} kartu tersaring dari ${counts[category] ?? 0}`
                          : `${counts[category] ?? 0} kartu aktif di board ini`
                }}</span>
                <!-- Nilai yang TERSARING — cuma saat filter aktif. Tanpa ini, "Estimasi
                     board" yang diam saat chip dipilih terbaca seperti angka macet. -->
                <span v-if="anyFilterAktif && !showArchived && !isKanban" class="text-xs font-semibold text-emerald-800">
                    · {{ rp(boardValue) }} tersaring
                </span>
                <button
                    title="Muat ulang"
                    class="ml-auto inline-flex items-center gap-1 bg-white/70 hover:bg-white border border-slate-200 text-slate-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                    @click="router.reload()"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>
                    Refresh
                </button>
            </div>

            <!-- Kolom — drag utk atur urutan (ala Trello).
                 group 'columns' SENGAJA beda dari 'kanban' milik kartu: nama grup yang sama
                 berarti kartu bisa dijatuhkan ke daftar kolom (& sebaliknya).

                 Kolom bisa dicengkeram dari MANA SAJA (header, nominal, ruang kosong, tepi),
                 kecuali yang disebut di `filter`. Pakai filter, BUKAN handle sempit:
                   - `.col-cards` (daftar kartu) WAJIB difilter. Selektor item SortableJS =
                     `[data-draggable]` (vuedraggable.common.js:4400) dan KARTU pun memakainya
                     dari draggable bersarang di dalam kolom — tanpa filter ini, sortable kolom
                     menelusuri ke atas dari titik klik, menemukan KARTU, lalu menyeretnya sbg
                     "kolom" → drag kartu rusak total.
                   - `button, a` supaya menekan tombol +/menu/link tak terbaca sbg awal drag.
                 SortableJS mengecek filter SEBELUM handle & mencocokkannya sampai ke leluhur,
                 jadi klik di dalam kartu tak pernah sampai ke sortable kolom.

                 prevent-on-filter=false WAJIB: default-nya true = preventDefault() di mousedown,
                 yang mematikan klik tombol DAN drag kartu (sortable kartu menerima event yg sama).

                 force-auto-scroll-fallback: plugin AutoScroll sudah ter-mount default
                 (Sortable.js:3775) TAPI jalur non-fallback tak jalan dgn drag HTML5 native di
                 Chrome (lihat syarat di Sortable.js:2836) → board diam saat diseret ke tepi. -->
            <!-- Preview Objective OKR (kuartal panel) — gaya kartu disamakan dgn
                 halaman OKR: label OBJECTIVE N, chip prioritas, judul tebal,
                 progress bar (angka sama dgn OKR). Diklik → /okr?q=..&focus=id
                 (OKR scroll + sorot objektifnya). Read-only di sini. -->
            <div v-if="objectives && objectives.length" class="mb-3">
                <div class="flex items-center gap-2 mb-2 px-0.5">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0-1.657 1.343-3 3-3s3 1.343 3 3M4.5 12a7.5 7.5 0 1115 0 7.5 7.5 0 01-15 0zm7.5-4v.01M12 12l3 3" />
                    </svg>
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-600">Objectives · {{ quarter.label }}</span>
                    <Link :href="`/okr?q=${quarter.key}`" class="ml-auto text-xs font-semibold text-brand-600 hover:underline">Buka OKR →</Link>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Link
                        v-for="(o, i) in objectives"
                        :key="o.id"
                        :href="`/okr?q=${quarter.key}&focus=${o.id}`"
                        class="group block bg-white rounded-xl border border-slate-200/90 shadow-xs p-4 transition hover:border-brand-300 hover:shadow-sm"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-red-600">OBJECTIVE {{ i + 1 }}</span>
                            <span v-if="o.priority" class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-700 uppercase">{{ o.priority.name }}</span>
                            <span v-if="objDone(o)" class="ml-auto inline-flex items-center gap-1 text-[10px] font-extrabold text-emerald-700">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                Selesai
                            </span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span v-if="objDone(o)" class="mt-0.5 w-5 h-5 rounded-md bg-emerald-500 text-white flex items-center justify-center shrink-0 font-extrabold text-[10px]">✓</span>
                            <h3 :class="['text-sm font-extrabold tracking-tight leading-snug line-clamp-2', objDone(o) ? 'text-emerald-900 line-through' : 'text-slate-900 group-hover:text-brand-700']">{{ o.title }}</h3>
                        </div>
                        <!-- Progress bar ala kartu Objective OKR -->
                        <div class="mt-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] text-slate-400">{{ o.kr_count }} KR</span>
                                <span class="text-[11px] font-bold" :class="objDone(o) ? 'text-emerald-600' : 'text-slate-500'">{{ o.progress === null ? '—' : o.progress + '%' }}</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="objDone(o) ? 'bg-emerald-500' : 'bg-brand-500'"
                                    :style="{ width: Math.min(100, Math.max(0, o.progress || 0)) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Scrollbar atas tersinkron dengan board. `sticky top-0` menempelkannya
                 ke tepi atas viewport: sejauh apa pun halaman digulir ke bawah, batang
                 geser kanan-kiri tetap kelihatan — jadi tak perlu turun ke kartu paling
                 bawah dulu untuk bergeser ke kolom kanan. bg + z supaya tak tembus kolom. -->
            <div
                ref="topScroll"
                class="overflow-x-auto h-4 mb-2 sticky top-0 z-10 bg-brand-50"
                @scroll="syncScroll($event.target, boardScroll)"
            >
                <div ref="topScrollInner" class="h-px"></div>
            </div>
            <div ref="boardScroll" class="max-h-[65vh] overflow-auto pb-4" @scroll="syncScroll($event.target, topScroll)">
                <div class="flex gap-3 min-w-max">
                    <draggable
                        :list="colOrder"
                        :group="{ name: 'columns' }"
                        item-key="key"
                        :disabled="colDragDisabled"
                        filter="button, a, .col-cards"
                        :prevent-on-filter="false"
                        :force-auto-scroll-fallback="true"
                        :scroll-sensitivity="90"
                        :scroll-speed="14"
                        class="flex gap-3"
                        ghost-class="drag-ghost"
                        :animation="180"
                        @change="onColumnChange"
                    >
                        <!-- JANGAN taruh komentar/elemen apa pun di dalam <template #item> sebelum
                     <div> di bawah: Vue mengubah komentar HTML jadi comment VNode, slot item
                     jadi 2 root node, dan vuedraggable melempar "Item slot must have only one
                     child" (vuedraggable.common.js:4617). Compiler MEMBUANG komentar di build
                     produksi tapi MEMPERTAHANKANNYA di dev → `npm run build` tak akan pernah
                     menangkapnya, cuma layar dev yang mati. Komentar taruh di sini, atau di
                     dalam <div>-nya. -->
                        <template #item="{ element: col }">
                            <div class="w-72 flex-shrink-0 flex flex-col bg-white border border-brand-100 rounded-2xl shadow-sm p-3">
                                <!-- flex flex-col = rangka supaya area kartu (.col-cards) bisa memanjang
                             memenuhi sisa tinggi kolom. Deretan kolom sudah `flex` dgn
                             align-items:stretch bawaan, jadi tiap kolom setinggi kolom tertinggi —
                             tanpa rangka ini sisa tingginya cuma ruang putih mati yang tak
                             menerima jatuhan kartu. -->
                                <!-- Header kolom: nama stage + total nilai & jml deal (ala Pipedrive).
                             cursor-grab cuma DI SINI walau kolom bisa diseret dari mana saja:
                             memasangnya di akar kolom bikin kursor grab ikut muncul di sela-sela
                             kartu, tempat drag kolom justru tak aktif (difilter) — sinyal palsu. -->
                                <div
                                    :class="[
                                        'flex items-start justify-between mb-3',
                                        colDragDisabled ? '' : 'cursor-grab active:cursor-grabbing',
                                    ]"
                                >
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span :class="['w-2.5 h-2.5 rounded-full', col.color]"></span>
                                            <h2 class="text-sm font-bold text-slate-700">{{ col.name }}</h2>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-0.5 pl-4.5">
                                            <span v-if="!isKanban" class="font-semibold text-slate-500">{{
                                                rpShort(colValue(col.key))
                                            }}</span>
                                            <span v-if="!isKanban"> · </span>{{ cardCount(col.key) }} {{ isKanban ? 'task' : 'deal' }}
                                        </p>
                                    </div>
                                    <div v-if="canManage && !showArchived" class="flex items-center gap-0.5">
                                        <button
                                            title="Tambah task"
                                            class="w-6 h-6 flex items-center justify-center rounded-md bg-brand-50 hover:bg-brand-100 text-brand-600 font-bold leading-none transition"
                                            @click="openAdd(col.key)"
                                        >
                                            +
                                        </button>
                                        <div v-if="canManageStructure" class="relative">
                                            <button
                                                title="Menu kolom"
                                                class="w-6 h-6 flex items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 transition"
                                                @click.stop="colMenu = colMenu === col.key ? null : col.key"
                                            >
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4z"
                                                    />
                                                </svg>
                                            </button>
                                            <div
                                                v-if="colMenu === col.key"
                                                class="absolute right-0 top-7 z-20 w-36 bg-white border border-brand-100 rounded-xl shadow-lg py-1 text-sm"
                                            >
                                                <button
                                                    class="w-full text-left px-4 py-2 hover:bg-brand-50 text-slate-600"
                                                    @click="
                                                        colMenu = null;
                                                        openColEdit(col.id, col.name);
                                                    "
                                                >
                                                    Ubah nama
                                                </button>
                                                <button
                                                    class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600"
                                                    @click="
                                                        colMenu = null;
                                                        deleteColumn(col.id);
                                                    "
                                                >
                                                    Hapus kolom
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Checklist delegasi kolom: owner/manager menugaskan item ke user
                             (staff didahulukan). Staff hanya melihat item yang didelegasikan ke
                             dirinya (difilter server). Tiap item yg tampil pasti boleh dicentang
                             si penonton; controller tetap menegakkan otorisasi. -->
                                <div
                                    v-if="columnTasks && ((colTasks[col.id] && colTasks[col.id].length) || canManageStructure)"
                                    class="mb-3 rounded-xl border border-slate-200 bg-slate-50/70 p-2.5"
                                >
                                    <div v-if="colTasks[col.id] && colTasks[col.id].length" class="mb-1.5 flex items-center gap-1.5 px-0.5">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7l2 2 4-4" />
                                        </svg>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Checklist</span>
                                        <span class="ml-auto text-[10px] font-bold text-slate-400">{{ colDoneCount(col.id) }}/{{ colTasks[col.id].length }}</span>
                                    </div>
                                    <div v-if="colTasks[col.id] && colTasks[col.id].length" class="space-y-0.5">
                                        <div v-for="t in colTasks[col.id]" :key="t.id" class="group flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-white transition">
                                            <button type="button" class="shrink-0" title="Tandai selesai" @click="toggleColTask(t)">
                                                <svg v-if="t.done" class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                                <svg v-else class="w-4 h-4 text-slate-300 hover:text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="9" />
                                                </svg>
                                            </button>
                                            <span class="flex-1 text-xs leading-tight" :class="t.done ? 'line-through text-slate-400' : 'text-slate-600'">{{ t.title }}</span>
                                            <span
                                                v-if="t.assignee"
                                                class="shrink-0 w-5 h-5 rounded-full bg-brand-100 text-brand-700 text-[9px] font-bold flex items-center justify-center ring-1 ring-white"
                                                :title="'Ditugaskan: ' + t.assignee"
                                            >{{ initialsOf(t.assignee) }}</span>
                                            <button
                                                v-if="canManageStructure"
                                                type="button"
                                                class="shrink-0 opacity-0 group-hover:opacity-100 text-slate-300 hover:text-red-500 transition"
                                                title="Hapus"
                                                @click="deleteColTask(t)"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Delegasi baru: owner/manager saja -->
                                    <div v-if="canManageStructure" class="mt-1.5">
                                        <button
                                            v-if="colTaskAddFor !== col.id"
                                            type="button"
                                            class="flex w-full items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-2 py-1.5 text-[11px] font-semibold text-slate-500 transition hover:border-brand-300 hover:bg-white hover:text-brand-600"
                                            @click="openColTaskAdd(col)"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                            Delegasikan task
                                        </button>
                                        <form v-else class="space-y-2 rounded-xl border border-brand-200 bg-white p-2 shadow-xs" @submit.prevent="submitColTask">
                                            <input
                                                v-model="colTaskForm.title"
                                                maxlength="120"
                                                placeholder="Apa yang harus dikerjakan?"
                                                :ref="(el) => el && el.focus()"
                                                class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200"
                                            />
                                            <div class="relative">
                                                <span
                                                    v-if="colTaskForm.assigned_to"
                                                    class="pointer-events-none absolute left-2 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-brand-100 text-[9px] font-bold text-brand-700"
                                                >{{ initialsOf((assignableUsers.find((u) => u.id === colTaskForm.assigned_to) || {}).name) }}</span>
                                                <select
                                                    v-model="colTaskForm.assigned_to"
                                                    :class="['w-full rounded-lg border border-slate-200 bg-white py-1.5 pr-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-200', colTaskForm.assigned_to ? 'pl-8' : 'pl-2.5 text-slate-400']"
                                                >
                                                    <option value="" disabled>Pilih penanggung jawab…</option>
                                                    <option v-for="u in assignableUsers" :key="u.id" :value="u.id">{{ u.name }} · {{ roleLabel(u.role) }}</option>
                                                </select>
                                            </div>
                                            <p v-if="!assignableUsers.length" class="text-[10px] text-amber-600">Belum ada user untuk ditugasi.</p>
                                            <div class="flex items-center gap-1.5 pt-0.5">
                                                <button
                                                    type="submit"
                                                    :disabled="!colTaskForm.title.trim() || !colTaskForm.assigned_to"
                                                    class="rounded-lg bg-brand-600 px-3 py-1.5 text-[11px] font-bold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    Delegasikan
                                                </button>
                                                <button type="button" class="px-2 py-1.5 text-[11px] font-semibold text-slate-400 hover:text-slate-600" @click="colTaskAddFor = null">Batal</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Daftar kartu (drag via vuedraggable, ala Trello).
                             Kelas `col-cards` dibaca oleh filter draggable KOLOM di atas —
                             menandai "di sini drag kolom tak berlaku". Jangan dihapus. -->
                                <div class="col-cards flex-1 flex flex-col min-h-[120px] rounded-xl p-2 bg-brand-50/60">
                                    <!-- Di ATAS draggable, bukan di bawahnya: draggable sekarang `flex-1`
                                 (memanjang), jadi kalau teks ini ditaruh sesudahnya dia terdorong
                                 ke dasar kolom & terlihat melayang jauh dari judulnya. -->
                                    <p v-if="cardCount(col.key) === 0" class="text-center text-xs text-slate-400 py-6">— no tasks —</p>
                                    <draggable
                                        :list="cols[col.key]"
                                        :group="{ name: 'kanban' }"
                                        item-key="id"
                                        :disabled="dragDisabled"
                                        :force-auto-scroll-fallback="true"
                                        :scroll-sensitivity="90"
                                        :scroll-speed="14"
                                        class="space-y-2.5 flex-1"
                                        ghost-class="drag-ghost"
                                        :animation="180"
                                        @change="onCardChange($event, col.key)"
                                    >
                                        <template #item="{ element: card }">
                                            <div
                                                :class="[
                                                    'group border rounded-xl p-3 shadow-sm hover:shadow-md transition',
                                                    isUrgent(card)
                                                        ? 'bg-white border-red-300 ring-1 ring-red-200'
                                                        : 'bg-white border-brand-100 hover:border-brand-200',
                                                    showArchived
                                                        ? 'opacity-70 cursor-pointer'
                                                        : canManage
                                                          ? 'cursor-grab active:cursor-grabbing'
                                                          : 'cursor-pointer',
                                                ]"
                                                @click="openDetail(card)"
                                            >
                                                <!-- Kategori dan aksi berada dalam satu baris agar tidak
                                     menciptakan ruang kosong tambahan sebelum judul. -->
                                                <div
                                                    v-if="card.labels?.length || canManage"
                                                    class="mb-1.5 flex min-h-5 items-start justify-between gap-2"
                                                >
                                                    <div class="flex flex-wrap gap-1">
                                                        <span
                                                            v-for="(lb, li) in card.labels || []"
                                                            :key="li"
                                                            :class="[
                                                                'rounded px-2 py-0.5 text-[10px] font-semibold uppercase text-white',
                                                                lb.color,
                                                            ]"
                                                        >
                                                            {{ lb.name }}
                                                        </span>
                                                    </div>
                                                    <button
                                                        v-if="canManage"
                                                        title="Hapus kartu"
                                                        class="flex-shrink-0 p-1 -m-1 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 opacity-0 group-hover:opacity-100 transition"
                                                        @click.stop="deleteCard(card)"
                                                    >
                                                        <svg
                                                            class="w-5 h-5"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"
                                                            />
                                                        </svg>
                                                    </button>
                                                </div>
                                                <p class="font-semibold text-sm leading-snug mb-2 text-slate-700">{{ card.endorse }}</p>

                                                <!-- Meta: urgent, deadline, deskripsi, komentar, lampiran -->
                                                <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                                    <span
                                                        v-if="card.deadline"
                                                        :class="[
                                                            'inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded',
                                                            card.deadline < todayStr()
                                                                ? 'bg-red-100 text-red-700 font-semibold'
                                                                : 'bg-slate-100 text-slate-600',
                                                        ]"
                                                    >
                                                        <svg
                                                            class="w-3 h-3"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"
                                                            />
                                                        </svg>
                                                        {{ card.deadline }}
                                                    </span>
                                                    <span
                                                        v-if="card.score !== null"
                                                        class="inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700"
                                                    >
                                                        {{ card.score }} poin
                                                    </span>
                                                    <span
                                                        v-if="card.revisi > 0"
                                                        class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-orange-500 text-white"
                                                        title="Nomor revisi"
                                                        >REVISI {{ card.revisi }}</span
                                                    >
                                                    <span
                                                        class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700"
                                                        title="Tanggal kartu dibuat"
                                                    >
                                                        Dibuat {{ fmtCreated(card.created_date) }}
                                                    </span>
                                                    <span
                                                        v-if="KETEPATAN[card.ketepatan]"
                                                        :class="[
                                                            'inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded',
                                                            KETEPATAN[card.ketepatan].cls,
                                                        ]"
                                                        :title="
                                                            card.completed_at
                                                                ? `Selesai ${fmtCreated(card.completed_at)}`
                                                                : 'Belum selesai & deadline sudah lewat'
                                                        "
                                                    >
                                                        {{ KETEPATAN[card.ketepatan].label }}
                                                    </span>
                                                    <span
                                                        v-if="card.description"
                                                        class="inline-flex items-center text-slate-400"
                                                        title="Ada deskripsi"
                                                        ><svg
                                                            class="w-3.5 h-3.5"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M4 6h16M4 12h16M4 18h10"
                                                            /></svg
                                                    ></span>
                                                    <span
                                                        v-if="card.comment_count > 0"
                                                        class="inline-flex items-center gap-0.5 text-[10px] text-slate-400"
                                                        ><svg
                                                            class="w-3.5 h-3.5"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 01-13.5 7.8L3 21l1.2-4.5A9 9 0 1121 12z"
                                                            /></svg
                                                        >{{ card.comment_count }}</span
                                                    >
                                                    <span
                                                        v-if="card.attachment_count > 0"
                                                        class="inline-flex items-center gap-0.5 text-[10px] text-slate-400"
                                                        ><svg
                                                            class="w-3.5 h-3.5"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                                                            /></svg
                                                        >{{ card.attachment_count }}</span
                                                    >
                                                </div>

                                                <!-- Output tags -->
                                                <div v-if="isPipeline && card.outputs.length" class="flex flex-wrap gap-1 mb-2">
                                                    <span
                                                        v-for="o in card.outputs"
                                                        :key="o"
                                                        class="text-[10px] px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 border border-brand-200"
                                                        >{{ o }}</span
                                                    >
                                                </div>

                                                <!-- Badge jenis + akun + pembayaran -->
                                                <div class="flex items-center gap-1.5 text-[10px] mb-1.5">
                                                    <span
                                                        v-if="isPipeline && card.jenis_label"
                                                        class="font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200"
                                                        >{{ card.jenis_label }}</span
                                                    >
                                                    <span
                                                        v-if="isPipeline"
                                                        :class="['font-semibold px-2 py-0.5 rounded-full', card.account_color]"
                                                        >{{ card.account }}</span
                                                    >
                                                    <span
                                                        v-if="isPipeline"
                                                        :class="[
                                                            'font-semibold px-2 py-0.5 rounded-full',
                                                            card.payment_status === 'lunas'
                                                                ? 'bg-emerald-600 text-white'
                                                                : card.payment_status === 'dp'
                                                                  ? 'bg-amber-400 text-amber-900'
                                                                  : 'bg-red-600 text-white',
                                                        ]"
                                                        >{{ card.payment }}</span
                                                    >
                                                    <span
                                                        v-if="isPipeline && card.dp_count > 0"
                                                        class="font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200"
                                                        title="Sudah bayar DP berapa kali"
                                                        >DP {{ card.dp_count }}×</span
                                                    >
                                                </div>

                                                <div v-if="isPipeline && card.dp_count > 0" class="mb-1.5">
                                                    <div class="flex items-center justify-between text-xs text-slate-500 mb-0.5">
                                                        <span class="font-medium">DP masuk</span>
                                                        <span class="font-semibold text-slate-700"
                                                            >{{ rp(cardDpPaid(card)) }} / {{ rp(cardValue(card)) }}</span
                                                        >
                                                    </div>
                                                    <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                                        <div
                                                            class="h-full rounded-full bg-amber-400"
                                                            :style="{
                                                                width:
                                                                    Math.min(
                                                                        100,
                                                                        cardValue(card) > 0
                                                                            ? (cardDpPaid(card) / cardValue(card)) * 100
                                                                            : 0,
                                                                    ) + '%',
                                                            }"
                                                        ></div>
                                                    </div>
                                                </div>

                                                <!-- PJ + nilai deal + link -->
                                                <div
                                                    class="flex items-center justify-between gap-2 text-[10px] pt-1.5 border-t border-brand-50"
                                                >
                                                    <span v-if="card.assignee" class="flex items-center gap-1.5 text-slate-500 truncate">
                                                        <span
                                                            class="w-4 h-4 flex-shrink-0 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-[9px] font-bold"
                                                            >{{ card.assignee.charAt(0).toUpperCase() }}</span
                                                        >
                                                        <span class="truncate font-medium">{{ card.assignee }}</span>
                                                    </span>
                                                    <span v-else class="text-slate-300 italic">belum ditugaskan</span>
                                                    <span
                                                        v-if="card.created_by_name"
                                                        class="text-slate-400 truncate"
                                                        :title="'Dibuat oleh ' + card.created_by_name"
                                                    >
                                                        oleh {{ card.created_by_name }}
                                                    </span>
                                                    <span class="flex items-center gap-1.5 flex-shrink-0">
                                                        <a
                                                            v-if="card.link"
                                                            :href="card.link"
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            class="flex items-center gap-0.5 text-brand-600 hover:text-brand-800 font-medium"
                                                            @click.stop
                                                        >
                                                            <svg
                                                                class="w-3 h-3"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                stroke-width="2"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <path
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                                                />
                                                            </svg>
                                                            Link
                                                        </a>
                                                        <span
                                                            v-if="isPipeline"
                                                            :class="[
                                                                'font-bold text-xs',
                                                                cardValue(card) > 0 ? 'text-slate-700' : 'text-slate-300',
                                                            ]"
                                                            >{{ rp(cardValue(card)) }}</span
                                                        >
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </draggable>
                                </div>
                            </div>
                        </template>
                    </draggable>

                    <!-- Tambah kolom — SIBLING di luar <draggable>, sengaja bukan slot #footer.
                     Slot footer sebenarnya sah (footer tak diberi data-draggable, jadi tak
                     ikut jadi item), tapi menaruhnya di luar berarti tombol ini tak punya
                     urusan sama sekali dgn SortableJS — satu variabel dugaan lebih sedikit
                     saat tombolnya bermasalah. -->
                    <div v-if="canManageStructure && !showArchived" class="w-64 flex-shrink-0">
                        <button
                            class="w-full flex items-center gap-2 bg-white/70 hover:bg-white border border-dashed border-brand-200 hover:border-brand-300 text-slate-500 hover:text-brand-700 rounded-2xl px-4 py-3 text-sm font-semibold transition"
                            @click="
                                colForm.board_key = category;
                                colForm.name = '';
                                colCreateOpen = true;
                            "
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
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
                    <h2 class="text-lg font-bold text-brand-800">
                        Kartu Baru <span class="text-sm font-normal text-slate-400">· {{ colNames[editForm.progressKey] }}</span>
                    </h2>
                </div>
                <div v-else>
                    <p class="text-[10px] text-slate-400 font-mono">{{ detailCard.code }}</p>
                    <h2 class="text-lg font-bold flex items-center gap-2 text-brand-800">
                        {{ detailCard.endorse }}
                        <span
                            v-if="isUrgent(detailCard)"
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-500 text-white no-underline"
                            >URGENT</span
                        >
                        <span
                            v-if="detailCard.archived"
                            class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 no-underline"
                            >ARSIP</span
                        >
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Arsip cuma untuk kartu yang sudah ada -->
                    <button
                        v-if="canManage && detailCard"
                        :title="detailCard.archived ? 'Kembalikan dari arsip' : 'Arsipkan kartu'"
                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                        @click="archiveCard(detailCard)"
                    >
                        {{ detailCard.archived ? 'Kembalikan' : 'Arsipkan' }}
                    </button>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none" @click="closeCard">
                        &times;
                    </button>
                </div>
            </div>

            <!-- Kontak lead yang bisa langsung dihubungi. Tampil utk kartu yg sudah ada
                 (bukan saat membuat) di mode form MAUPUN read-only, jadi manager/admin pun
                 dapat tautan klik-hubungi, bukan cuma kolom isian. -->
            <div
                v-if="isPipeline && detailCard && (detailCard.kontak_wa || detailCard.kontak_gmail || detailCard.kontak_ig)"
                class="flex flex-wrap gap-2 mb-3"
            >
                <a
                    v-if="detailCard.kontak_wa"
                    :href="waLink(detailCard.kontak_wa)"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100"
                    >WA · {{ detailCard.kontak_wa }}</a
                >
                <a
                    v-if="detailCard.kontak_gmail"
                    :href="'mailto:' + detailCard.kontak_gmail"
                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-lg bg-red-50 text-red-700 border border-red-200 hover:bg-red-100"
                    >Gmail · {{ detailCard.kontak_gmail }}</a
                >
                <a
                    v-if="detailCard.kontak_ig"
                    :href="igLink(detailCard.kontak_ig)"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-lg bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 hover:bg-fuchsia-100"
                    >IG · {{ detailCard.kontak_ig }}</a
                >
            </div>

            <!-- Form lengkap (manager) — sama persis untuk buat & edit -->
            <form v-if="canManage" class="grid grid-cols-2 gap-3 text-sm mb-2" @submit.prevent="submitCard">
                <label class="col-span-2 block font-medium text-slate-600"
                    >{{ isPipeline ? 'Judul / Endorse' : 'Judul kartu' }}
                    <input
                        v-model="editForm.endorse"
                        required
                        :autofocus="creating"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                </label>
                <label class="col-span-2 block font-medium text-slate-600"
                    >Deskripsi
                    <textarea
                        v-model="editForm.description"
                        v-auto-resize
                        rows="3"
                        placeholder="Detail task…"
                        class="mt-1 w-full max-h-80 overflow-y-auto resize-y border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    ></textarea>
                </label>
                <label class="block font-medium text-slate-600"
                    >Deadline
                    <input
                        v-model="editForm.deadline"
                        type="date"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                </label>
                <label
v-if="isKanban && isOwner"
class="block font-medium text-slate-600"
                    >Score
                    <input
                        v-model="editForm.score"
                        type="number"
                        min="0"
                        max="100"
                        placeholder="0–100"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <span class="mt-1 block text-[10px] font-normal text-slate-400"
                        >Target total per anggota minimal 100 setiap bulan deadline.</span
                    >
                    <span v-if="editForm.errors.score" class="mt-1 block text-xs text-red-600">{{ editForm.errors.score }}</span>
                </label>
                <label class="block font-medium text-slate-600"
                    >Revisi
                    <select
                        v-model.number="editForm.revisi"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-300 outline-none"
                    >
                        <option :value="0">— tanpa revisi —</option>
                        <option :value="1">Revisi 1</option>
                        <option :value="2">Revisi 2</option>
                        <option :value="3">Revisi 3</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600"
                    >Kolom
                    <select
                        v-model="editForm.progressKey"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    >
                        <option v-for="c in columns" :key="c.key" :value="c.key">{{ c.name }}</option>
                    </select>
                </label>
                <label
v-if="isPipeline"
class="block font-medium text-slate-600"
                    >Account
                    <select
                        v-model="editForm.account"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    >
                        <option v-for="(v, k) in accounts" :key="k" :value="k">{{ v }}</option>
                    </select>
                </label>
                <label
v-if="isPipeline"
class="block font-medium text-slate-600"
                    >Jenis
                    <select
                        v-model="editForm.jenis"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    >
                        <option value="">— tanpa jenis —</option>
                        <option v-for="(v, k) in jenisList" :key="k" :value="k">{{ v }}</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600"
                    >Penanggung Jawab
                    <select
                        v-model="editForm.assigned_to"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    >
                        <option value="">— belum ditugaskan —</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </label>
                <label
v-if="isPipeline"
class="block font-medium text-slate-600"
                    >Payment
                    <select
                        v-model="editForm.payment_status"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    >
                        <option value="belum">Belum</option>
                        <option value="dp">DP</option>
                        <option value="lunas">Lunas</option>
                    </select>
                </label>
                <label
v-if="isPipeline"
class="block font-medium text-slate-600"
                    >Jumlah IDR
                    <input
                        v-model="editForm.amount_idr"
                        type="number"
                        step="0.01"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                </label>
                <label
v-if="isPipeline"
class="block font-medium text-slate-600"
                    >Jumlah USD
                    <input
                        v-model="editForm.amount_usd"
                        type="number"
                        step="0.01"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                </label>
                <!-- Cicilan DP (IDR). Kosongkan slot yang belum dibayar; badge "DP N×" di kartu
                     menghitung slot yang terisi. -->
                <div v-if="isPipeline" class="col-span-2 grid grid-cols-3 gap-3">
                    <label class="block font-medium text-slate-600"
                        >DP 1
                        <input
                            v-model="editForm.dp1"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                        />
                    </label>
                    <label class="block font-medium text-slate-600"
                        >DP 2
                        <input
                            v-model="editForm.dp2"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                        />
                    </label>
                    <label class="block font-medium text-slate-600"
                        >DP 3
                        <input
                            v-model="editForm.dp3"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                        />
                    </label>
                </div>
                <div class="col-span-2 font-medium text-slate-600">
                    <label for="card-link">{{ isPipeline ? 'Link Video' : 'Tautan' }}</label>
                    <div class="mt-1 flex gap-2">
                        <input
                            id="card-link"
                            v-model="editForm.link"
                            type="url"
                            placeholder="https://…"
                            class="min-w-0 flex-1 border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                        />
                        <!-- Saat URL sudah terisi, bisa dibuka tanpa menyimpan/menutup form. -->
                        <a
                            v-if="editForm.link"
                            :href="editForm.link"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center px-4 py-2 rounded-xl bg-brand-50 border border-brand-200 text-brand-700 text-xs font-semibold hover:bg-brand-100"
                        >
                            Buka tautan ↗
                        </a>
                    </div>
                </div>
                <!-- Kontak lead: WA / Gmail / DM Instagram. Cuma di Sales Pipeline.
                     String bebas — WA boleh '0812…'/'+62…', IG boleh '@akun'; server tak
                     memvalidasi ketat, jadi placeholder saja yang memandu format. -->
                <div v-if="isPipeline" class="col-span-2 grid grid-cols-3 gap-3">
                    <label class="block font-medium text-slate-600"
                        >WhatsApp
                        <input
                            v-model="editForm.kontak_wa"
                            placeholder="0812…"
                            class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                        />
                    </label>
                    <label class="block font-medium text-slate-600"
                        >Gmail
                        <input
                            v-model="editForm.kontak_gmail"
                            placeholder="nama@gmail.com"
                            class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                        />
                    </label>
                    <label class="block font-medium text-slate-600"
                        >DM Instagram
                        <input
                            v-model="editForm.kontak_ig"
                            placeholder="@akun"
                            class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                        />
                    </label>
                </div>
                <!-- Output -->
                <div v-if="isPipeline" class="col-span-2">
                    <p class="font-medium text-slate-600 mb-1.5">Output</p>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="out in outputs"
                            :key="out.id"
                            class="inline-flex items-center gap-1.5 bg-brand-50 border border-brand-100 rounded-lg px-3 py-1.5 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :checked="editForm.outputs.includes(out.id)"
                                class="accent-brand-600"
                                @change="toggleOutput(out.id)"
                            />
                            {{ out.name }}
                        </label>
                    </div>
                </div>
                <!-- Dua kategori independen; masing-masing boleh pilih satu. -->
                <div class="col-span-2">
                    <div class="flex items-center justify-between mb-1.5">
                        <p class="font-medium text-slate-600">Kategori kartu</p>
                        <button
                            v-if="isOwner"
                            type="button"
                            class="text-xs text-brand-600 hover:underline font-medium"
                            @click="labelManageOpen = true"
                        >
                            Kelola kategori
                        </button>
                    </div>
                    <div v-for="group in [1, 2]" :key="group" class="mt-2">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ LABEL_GROUP_NAMES[group] }}</p>
                        <!-- Tanda selesai kartu (Sales) — chip Status Pekerjaan, gaya sama spt label Kanban. -->
                        <button
                            v-if="group === 1 && isPipeline && detailCard"
                            type="button"
                            role="radio"
                            :aria-checked="!!detailCard.completed_at"
                            :class="[
                                'mb-2 flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition',
                                detailCard.completed_at
                                    ? 'border-brand-400 bg-brand-50 text-slate-700'
                                    : 'border-slate-200 text-slate-500 hover:bg-slate-50',
                            ]"
                            @click="toggleDone(detailCard)"
                        >
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span><span>Selesai</span><span v-if="detailCard.completed_at">✓</span>
                        </button>
                        <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="`Kategori ${group}`">
                            <button
                                v-for="lp in labelGroups[group]"
                                :key="lp.id"
                                type="button"
                                role="radio"
                                :aria-checked="hasLabel(lp.name)"
                                :class="[
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition',
                                    hasLabel(lp.name)
                                        ? 'border-brand-400 bg-brand-50 text-slate-700'
                                        : 'border-slate-200 text-slate-500 hover:bg-slate-50',
                                ]"
                                @click="toggleLabel(lp)"
                            >
                                <span :class="['w-3 h-3 rounded-full', lp.color]"></span><span>{{ lp.name }}</span
                                ><span v-if="hasLabel(lp.name)">✓</span>
                            </button>
                            <p v-if="!labelGroups[group].length && !(group === 1 && isPipeline && detailCard)" class="text-xs text-slate-400">Belum ada pilihan.</p>
                        </div>
                    </div>
                </div>
                <label class="col-span-2 block font-medium text-slate-600"
                    >Notes
                    <textarea
                        v-model="editForm.notes"
                        v-auto-resize
                        rows="2"
                        class="mt-1 w-full max-h-80 overflow-y-auto resize-y border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    ></textarea>
                </label>
                <!-- Lampiran opsional saat membuat kartu — filenya ikut di request buat-kartu. -->
                <div v-if="creating" class="col-span-2">
                    <p class="font-medium text-slate-600 mb-1">
                        Lampiran
                        <span class="font-normal text-slate-400 text-xs"
                            >(opsional · pilih file atau Ctrl/Cmd+V screenshot · maks 10 MB)</span
                        >
                    </p>
                    <input id="new-attach" type="file" class="hidden" @change="editForm.newAttachment = $event.target.files[0]" />
                    <div class="flex items-center gap-2">
                        <label
                            for="new-attach"
                            class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold transition"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                                />
                            </svg>
                            Pilih file
                        </label>
                        <span class="flex-1 text-xs text-slate-500 truncate">{{
                            editForm.newAttachment ? editForm.newAttachment.name : 'Belum ada file dipilih'
                        }}</span>
                    </div>
                    <p v-if="editForm.errors.newAttachment" class="text-xs text-red-600 mt-1">{{ editForm.errors.newAttachment }}</p>
                </div>
                <div class="col-span-2 flex justify-end gap-2">
                    <button
                        v-if="creating"
                        type="button"
                        class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50"
                        @click="closeCard"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60"
                    >
                        {{ creating ? 'Buat kartu' : 'Simpan perubahan' }}
                    </button>
                </div>
            </form>

            <!-- Ringkasan read-only (non-manager) -->
            <div v-else-if="detailCard" class="space-y-2 text-sm mb-2">
                <p v-if="detailCard.deadline">
                    <span class="font-medium text-slate-600">Deadline:</span>
                    <span :class="detailCard.deadline < todayStr() ? 'text-red-600 font-semibold' : 'text-slate-700'">{{
                        detailCard.deadline
                    }}</span>
                </p>
                <p v-if="detailCard.assignee"><span class="font-medium text-slate-600">PJ:</span> {{ detailCard.assignee }}</p>
                <p v-if="detailCard.score !== null"><span class="font-medium text-slate-600">Score:</span> {{ detailCard.score }} poin</p>
                <!-- Jejak pembuat & penyelesaian, untuk menjawab "siapa yang bikin"
                     dan "telat berapa" tanpa harus menebak dari riwayat. -->
                <p v-if="detailCard.created_by_name">
                    <span class="font-medium text-slate-600">Dibuat oleh:</span> {{ detailCard.created_by_name }}
                </p>
                <p v-if="detailCard.completed_at">
                    <span class="font-medium text-slate-600">Selesai:</span> {{ fmtCreated(detailCard.completed_at) }}
                </p>
                <p v-if="KETEPATAN[detailCard.ketepatan]">
                    <span class="font-medium text-slate-600">Ketepatan:</span>
                    <span :class="['ml-1 text-[11px] font-semibold px-1.5 py-0.5 rounded', KETEPATAN[detailCard.ketepatan].cls]">{{
                        KETEPATAN[detailCard.ketepatan].label
                    }}</span>
                </p>
                <div v-if="detailCard.labels.length" class="flex flex-wrap gap-1.5">
                    <span
                        v-for="(lb, li) in detailCard.labels"
                        :key="li"
                        :class="['text-[10px] text-white font-semibold px-2 py-0.5 rounded', lb.color]"
                        >{{ lb.name }}</span
                    >
                </div>
                <div>
                    <p class="font-medium text-slate-600">Deskripsi</p>
                    <p class="text-slate-700 whitespace-pre-line">{{ detailCard.description || '—' }}</p>
                </div>
                <a
                    v-if="detailCard.link"
                    :href="detailCard.link"
                    target="_blank"
                    rel="noreferrer"
                    class="text-brand-600 hover:underline text-sm"
                    >Buka link video →</a
                >
            </div>

            <div v-if="detailCard?.is_kr_master" class="border-t border-slate-100 pt-4 mt-3">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-semibold text-slate-700 text-sm">Delegasi tugas</p>
                    <span class="text-xs text-slate-400"
                        >{{ detailCard.task_progress.done }}/{{ detailCard.task_progress.total }} selesai</span
                    >
                </div>
                <div class="space-y-1.5">
                    <div
                        v-for="task in detailCard.tasks"
                        :key="task.id"
                        class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm"
                    >
                        <button
                            type="button"
                            :class="[
                                'h-4 w-4 rounded border flex-shrink-0',
                                task.done ? 'bg-emerald-500 border-emerald-500' : 'border-slate-300',
                            ]"
                            @click="toggleTask(task)"
                        ></button>
                        <span :class="['flex-1', task.done ? 'line-through text-slate-400' : 'text-slate-700']">{{ task.title }}</span>
                        <span class="text-[10px] text-slate-400">{{ task.assignee || 'Belum ada PIC' }}</span>
                        <span v-if="task.deadline" class="text-[10px] text-slate-400">{{ task.deadline }}</span>
                        <button v-if="canManage" type="button" class="text-slate-300 hover:text-red-500" @click="deleteTask(task)">
                            &times;
                        </button>
                    </div>
                </div>
                <form v-if="canManage" class="mt-2 grid grid-cols-2 gap-2" @submit.prevent="addTask">
                    <input
                        v-model="taskForm.title"
                        required
                        placeholder="Tugas baru…"
                        class="col-span-2 border border-slate-200 rounded-lg px-3 py-2 text-sm"
                    />
                    <select v-model="taskForm.assigned_to" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="">— pilih PIC —</option>
                        <option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option>
                    </select>
                    <input v-model="taskForm.deadline" type="date" class="border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                    <button type="submit" class="col-span-2 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">
                        Tambah tugas
                    </button>
                </form>
            </div>

            <!-- Lampiran — perlu id kartu, jadi baru muncul setelah kartunya ada -->
            <div v-if="detailCard" class="border-t border-slate-100 pt-4 mt-2">
                <p class="font-semibold text-slate-700 mb-2 text-sm">
                    Lampiran ({{ detailCard.attachments.length }})
                    <span
v-if="canManageStructure"
class="font-normal text-[11px] text-slate-400"
                        >· pilih file atau Ctrl/Cmd+V screenshot</span
                    >
                </p>
                <div class="space-y-1.5 mb-2">
                    <div
                        v-for="a in detailCard.attachments"
                        :key="a.id"
                        class="flex items-center gap-2 text-sm bg-slate-50 rounded-lg px-3 py-2"
                    >
                        <svg
                            class="w-4 h-4 text-slate-400 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                            />
                        </svg>
                        <a :href="a.url" target="_blank" rel="noreferrer" class="flex-1 text-brand-700 hover:underline truncate">{{
                            a.name
                        }}</a>
                        <span class="text-[10px] text-slate-400">{{ fmtSize(a.size) }}</span>
                        <button
                            v-if="canManageStructure"
                            class="text-slate-300 hover:text-red-500 text-lg leading-none"
                            @click="deleteAttachment(a.id)"
                        >
                            &times;
                        </button>
                    </div>
                    <p v-if="detailCard.attachments.length === 0" class="text-xs text-slate-400">Belum ada lampiran.</p>
                </div>
                <form v-if="canManageStructure" class="flex items-center gap-2" @submit.prevent="submitAttach">
                    <!-- input file disembunyikan, dipicu label bergaya tombol -->
                    <input id="attach-file" type="file" class="hidden" @change="attachForm.file = $event.target.files[0]" />
                    <label
                        for="attach-file"
                        class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                            />
                        </svg>
                        Pilih file
                    </label>
                    <span class="flex-1 text-xs text-slate-500 truncate">{{
                        attachForm.file ? attachForm.file.name : 'Belum ada file dipilih'
                    }}</span>
                    <button
                        type="submit"
                        :disabled="attachForm.processing || !attachForm.file"
                        class="px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold transition disabled:opacity-50"
                    >
                        Unggah
                    </button>
                </form>
                <p v-if="attachForm.errors.file" class="text-xs text-red-600 mt-1">{{ attachForm.errors.file }}</p>
            </div>

            <!-- Komentar — idem: butuh id kartu -->
            <div v-if="detailCard" class="border-t border-slate-100 pt-4 mt-4">
                <p class="font-semibold text-slate-700 mb-2 text-sm">Komentar ({{ detailCard.comments.length }})</p>
                <form class="flex gap-2 mb-3" @submit.prevent="submitComment">
                    <input
                        v-model="commentForm.body"
                        placeholder="Tulis komentar…"
                        class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <button
                        type="submit"
                        :disabled="commentForm.processing"
                        class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition disabled:opacity-60"
                    >
                        Kirim
                    </button>
                </form>
                <div class="space-y-2.5 max-h-60 overflow-y-auto">
                    <div v-for="c in detailCard.comments" :key="c.id" class="flex gap-2">
                        <div
                            class="w-7 h-7 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold flex-shrink-0"
                        >
                            {{ (c.user || '?').charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 bg-slate-50 rounded-xl px-3 py-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-700"
                                    >{{ c.user || 'User' }}<span class="ml-2 font-normal text-slate-400">{{ c.time }}</span></span
                                >
                                <button
                                    v-if="c.user_id === authUser.id || canManage"
                                    class="text-slate-300 hover:text-red-500 text-sm leading-none"
                                    @click="deleteComment(c.id)"
                                >
                                    &times;
                                </button>
                            </div>
                            <p class="text-sm text-slate-700 whitespace-pre-line">{{ c.body }}</p>
                        </div>
                    </div>
                    <p v-if="detailCard.comments.length === 0" class="text-xs text-slate-400">Belum ada komentar.</p>
                </div>
            </div>
        </ModalWrap>

        <!-- ===== Modal board baru ===== -->
        <!-- Kelola kategori — OWNER only. Secara internal masih memakai data label. -->
        <ModalWrap v-if="isOwner && labelManageOpen" width="max-w-md" @close="labelManageOpen = false">
            <h3 class="text-lg font-bold text-slate-800 mb-3">Kelola Kategori</h3>
            <div class="space-y-2 mb-4 max-h-72 overflow-y-auto">
                <div v-for="l in labels" :key="l.id" class="flex items-center gap-2">
                    <!-- Baris sedang diedit -->
                    <template v-if="labelEditId === l.id">
                        <span :class="['w-5 h-5 rounded-full flex-shrink-0', labelEditForm.color]"></span>
                        <select v-model.number="labelEditForm.group" class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm">
                            <option :value="1">Status Pekerjaan</option>
                            <option :value="2">Penanda Pekerjaan</option>
                        </select>
                        <select v-model="labelEditForm.color" class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm">
                            <option v-for="c in LABEL_COLORS" :key="c" :value="c">{{ c.replace('bg-', '').replace('-500', '') }}</option>
                        </select>
                        <input
                            v-model="labelEditForm.name"
                            class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 text-sm"
                            @keydown.enter.prevent="saveEditLabel"
                        />
                        <button type="button" class="text-xs font-semibold text-emerald-600 hover:underline" @click="saveEditLabel">
                            Simpan
                        </button>
                        <button type="button" class="text-xs text-slate-400 hover:underline" @click="labelEditId = null">Batal</button>
                    </template>
                    <!-- Baris tampilan -->
                    <template v-else>
                        <span :class="['w-4 h-4 rounded-full flex-shrink-0', l.color]"></span>
                        <span class="text-[10px] font-bold text-slate-400">{{ LABEL_GROUP_NAMES[groupForLabel(l)] }}</span>
                        <span class="flex-1 text-sm text-slate-700">{{ l.name }}</span>
                        <button type="button" class="text-xs text-brand-600 hover:underline" @click="startEditLabel(l)">Edit</button>
                        <button type="button" class="text-xs text-red-500 hover:underline" @click="deleteLabel(l.id)">Hapus</button>
                    </template>
                </div>
                <p v-if="!labels.length" class="text-sm text-slate-400">Belum ada kategori.</p>
            </div>
            <!-- Tambah kategori baru -->
            <div class="border-t border-slate-100 pt-3">
                <p class="text-xs font-semibold text-slate-500 mb-1.5">Tambah kategori</p>
                <div class="flex items-center gap-2">
                    <span :class="['w-5 h-5 rounded-full flex-shrink-0', labelForm.color]"></span>
                    <select v-model.number="labelForm.group" class="border border-slate-200 rounded-lg px-2 py-2 text-sm">
                        <option :value="1">Status Pekerjaan</option>
                        <option :value="2">Penanda Pekerjaan</option>
                    </select>
                    <select v-model="labelForm.color" class="border border-slate-200 rounded-lg px-2 py-2 text-sm">
                        <option v-for="c in LABEL_COLORS" :key="c" :value="c">{{ c.replace('bg-', '').replace('-500', '') }}</option>
                    </select>
                    <input
                        v-model="labelForm.name"
                        placeholder="Nama kategori…"
                        class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm"
                        @keydown.enter.prevent="addLabel"
                    />
                    <button
                        type="button"
                        :disabled="labelForm.processing"
                        class="px-3 py-2 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold disabled:opacity-50"
                        @click="addLabel"
                    >
                        Tambah
                    </button>
                </div>
                <p v-if="labelForm.errors.name" class="text-xs text-red-600 mt-1">{{ labelForm.errors.name }}</p>
            </div>
        </ModalWrap>

        <ModalWrap v-if="canManage && boardCreateOpen" width="max-w-sm" @close="boardCreateOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Board Baru</h2>
            <form class="space-y-3" @submit.prevent="submitBoardCreate">
                <input
                    v-model="boardForm.name"
                    required
                    autofocus
                    placeholder="Nama board…"
                    class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none"
                />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm"
                        @click="boardCreateOpen = false"
                    >
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal kolom baru ===== -->
        <ModalWrap v-if="canManage && colCreateOpen" width="max-w-sm" @close="colCreateOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Kolom Baru</h2>
            <form class="space-y-3" @submit.prevent="submitColCreate">
                <input
                    v-model="colForm.name"
                    required
                    autofocus
                    placeholder="Nama kolom…"
                    class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none"
                />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm"
                        @click="colCreateOpen = false"
                    >
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal ubah nama kolom ===== -->
        <ModalWrap v-if="canManage && colEditOpen" width="max-w-sm" @close="colEditOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Ubah Nama Kolom</h2>
            <form class="space-y-3" @submit.prevent="submitColEdit">
                <input
                    v-model="colForm.name"
                    required
                    autofocus
                    class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none"
                />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm"
                        @click="colEditOpen = false"
                    >
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal ubah nama board ===== -->
        <ModalWrap v-if="canManage && currentBoard && boardEditOpen" width="max-w-sm" @close="boardEditOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Ubah Nama Board</h2>
            <form class="space-y-3" @submit.prevent="submitBoardEdit">
                <input
                    v-model="boardForm.name"
                    required
                    autofocus
                    class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none"
                />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm"
                        @click="boardEditOpen = false"
                    >
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>
        <!-- ===== Modal Diagram Struktur Organisasi (Sesuai Diagram User) ===== -->
        <ModalWrap v-if="showOrgDiagram" width="max-w-6xl" @close="showOrgDiagram = false">
            <div class="space-y-6 p-2">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Diagram Struktur Organisasi &amp; Jobdesk Tim</h2>
                        <p class="text-xs text-slate-500">Hirarki tim dan pembagian peran di Kanban Sistem AI Preneur</p>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600 font-bold text-sm" @click="showOrgDiagram = false">✕</button>
                </div>

                <!-- Tree Diagram Component (Aesthetics Matching Diagram) -->
                <div class="overflow-x-auto py-6 px-4 bg-slate-50/50 rounded-2xl border border-slate-200/80">
                    <div class="min-w-[900px] flex flex-col items-center space-y-8">
                        <!-- Level 1: Owner -->
                        <div class="flex flex-col items-center">
                            <div
                                class="bg-white border-2 border-slate-800 rounded-2xl px-8 py-3 text-center shadow-sm hover:border-blue-600 transition-all"
                            >
                                <p class="text-sm font-extrabold text-slate-900">Freedie</p>
                                <p class="text-xs font-semibold text-slate-500">(Owner)</p>
                            </div>
                            <div class="w-0.5 h-8 bg-slate-400"></div>
                        </div>

                        <!-- Level 2: General Manager -->
                        <div class="flex flex-col items-center w-full">
                            <div
                                class="bg-white border-2 border-slate-800 rounded-2xl px-8 py-3 text-center shadow-sm hover:border-blue-600 transition-all z-10"
                            >
                                <p class="text-sm font-extrabold text-slate-900">Gilang</p>
                                <p class="text-xs font-semibold text-slate-500">(General Manager)</p>
                            </div>

                            <!-- Branching connector lines -->
                            <div class="w-full relative h-8">
                                <div class="absolute left-1/2 top-0 w-0.5 h-4 -translate-x-1/2 bg-slate-400"></div>
                                <div class="absolute top-4 left-[5%] right-[5%] h-0.5 bg-slate-400"></div>
                            </div>
                        </div>

                        <!-- Level 3: Team Members Grid / Tree Row -->
                        <div class="w-full flex justify-between gap-2 px-2">
                            <!-- ULi -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">ULi</p>
                                    <p class="text-[10px] font-medium text-slate-500">(Script Writer)</p>
                                </div>
                            </div>

                            <!-- Aisyah -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Aisyah</p>
                                    <p class="text-[10px] font-medium text-slate-500">(Script Writer)</p>
                                </div>
                            </div>

                            <!-- Christian -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Christian</p>
                                    <p class="text-[10px] font-medium text-slate-500">(Editor)</p>
                                </div>
                            </div>

                            <!-- Bram -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Bram</p>
                                    <p class="text-[10px] font-medium text-slate-500">(Editor)</p>
                                </div>
                            </div>

                            <!-- Apip -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Apip</p>
                                    <p class="text-[10px] font-medium text-slate-500">(Editor)</p>
                                </div>
                            </div>

                            <!-- Syifa -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Syifa</p>
                                    <p class="text-[10px] font-medium text-slate-500">(Editor)</p>
                                </div>
                            </div>

                            <!-- Fikri -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Fikri</p>
                                    <p class="text-[10px] font-medium text-slate-500">(Editor)</p>
                                </div>
                            </div>

                            <!-- Icha -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Icha</p>
                                    <p class="text-[10px] font-medium text-slate-500">(Designer)</p>
                                </div>
                            </div>

                            <!-- Audi -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Audi</p>
                                    <p class="text-[10px] font-medium text-slate-500">(AI Engineer)</p>
                                </div>
                            </div>

                            <!-- Ilham -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-0.5 h-4 bg-slate-400 -mt-8 mb-4"></div>
                                <div
                                    class="bg-white border border-slate-800 rounded-xl p-2.5 text-center shadow-2xs w-full hover:border-blue-500 transition-all"
                                >
                                    <p class="text-xs font-extrabold text-slate-900">Ilham</p>
                                    <p class="text-[10px] font-medium text-slate-500">(AI Engineer)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition"
                        @click="showOrgDiagram = false"
                    >
                        Tutup
                    </button>
                </div>
            </div>
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
.drag-ghost > * {
    visibility: hidden;
}
</style>

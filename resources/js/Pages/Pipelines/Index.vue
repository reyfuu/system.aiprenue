<script setup>
// Halaman Pipeline (versi Vue): tabel entri endorsement + filter (termasuk pilih board) + ringkasan + modal tambah/edit.
import { ref, reactive, computed } from 'vue';                    // state lokal (ref/reactive) & computed
import { Link, useForm, router, usePage } from '@inertiajs/vue3'; // helper navigasi & form Inertia
import Layout from '../../Layout.vue';                            // layout bersama (sidebar + toast)
import ModalWrap from '../../ModalWrap.vue';                      // pembungkus modal

// Buat board PIPELINE (terpisah dari board kanban)
const boardOpen = ref(false);
const boardForm = useForm({ name: '', section: '', type: 'pipeline' });
const submitBoard = () => boardForm.post('/boards', { onSuccess: () => (boardOpen.value = false) });

// Props dari controller (bentuk sama persis dengan versi React)
const props = defineProps({
    pipelines: Object,     // paginator Laravel: { data, links, total, from, to } — 10 entri/halaman
    category: String,      // kategori aktif
    counts: Object,        // jumlah entri per kategori (angka di dropdown board)
    categories: Object,    // peta key→label kategori
    outputs: Array,        // daftar output tersedia (id, name)
    summary: Object,       // ringkasan omzet/kurs/statistik
    filters: Object,       // nilai filter aktif dari server
    accounts: Object,      // peta key→label account
    progresses: Object,    // peta key→label progress
    payments: Object,      // peta key→label payment_status
    keGilang: Object,      // peta key→label ke_gilang
    staff: Array,          // daftar staff (dibawa dari controller)
});

// Ambil user login dari shared props
const page = usePage();
const auth = computed(() => page.props.auth); // reaktif terhadap shared props

// Helper format Rupiah: "Rp 1.234.567" (id-ID)
const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

// Format tanggal "13 Jul 2026" dari string ISO, atau em-dash bila kosong
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

// Warna badge per account (samakan dengan Pipeline::ACCOUNT_COLORS)
const ACCOUNT_COLORS = {
    fk: 'bg-brand-600 text-white',            // FK → brand
    ai_preneur: 'bg-slate-500 text-white',    // AI Preneur → abu-abu
};

// Warna badge per progress (samakan dengan blade $pc)
const PROGRESS_COLORS = {
    script: 'bg-purple-600 text-white',       // script → ungu
    editing: 'bg-brand-100 text-brand-700',   // editing → brand muda
    progress: 'bg-brand-600 text-white',      // progress → brand
    pending: 'bg-amber-400 text-amber-900',   // pending → amber
    done: 'bg-emerald-600 text-white',        // done → hijau
};

// Warna badge per payment_status (samakan dengan blade $yc)
const PAYMENT_COLORS = {
    lunas: 'bg-emerald-600 text-white',       // lunas → hijau
    dp: 'bg-amber-400 text-amber-900',        // dp → amber
    belum: 'bg-red-600 text-white',           // belum → merah
};

// State filter lokal (kontrol input sebelum navigasi), diinisialisasi dari props.filters
const f = reactive({
    category: props.category,                        // board/kategori aktif (dulu berupa tab)
    account: props.filters.account || '',            // filter account terpilih
    progress: props.filters.progress || '',          // filter progress terpilih
    payment_status: props.filters.payment_status || '', // filter payment terpilih
    output: props.filters.output || '',              // filter output terpilih
    search: props.filters.search || '',              // kata kunci pencarian
});

// Terapkan filter → navigasi GET Inertia (pertahankan state & scroll)
const applyFilters = (next = {}) => {
    Object.assign(f, next);                          // gabung perubahan ke state lokal
    router.get('/pipelines',
        { category: f.category, account: f.account, progress: f.progress, payment_status: f.payment_status, output: f.output, search: f.search },
        { preserveState: true, preserveScroll: true, replace: true }); // kirim ke server
};

// State modal tambah/edit
const open = ref(false);        // modal terbuka?
const mode = ref('create');     // 'create' | 'edit'
const editId = ref(null);       // id entri yang diedit

// Form Inertia untuk modal (nilai awal = blank)
const form = useForm({
    category: props.category,     // kategori aktif sebagai default
    account: 'fk',                // default account FK
    endorse: '',                  // nama endorse/produk
    outputs: [],                  // id output terpilih (array)
    progressKey: 'script',        // default progress (nama `progressKey`, bukan `progress`, agar tak bentrok properti bawaan useForm)
    payment_status: 'belum',      // default payment belum
    tanggal_posting: '',          // tanggal posting
    tanggal_payment: '',          // tanggal payment
    amount_idr: '',               // nominal IDR
    amount_usd: '',               // nominal USD
    notes: '',                    // catatan panjang
});

// Nilai kurs untuk estimasi konversi di modal
const rate = computed(() => props.summary.rate);

// Buka modal tambah: reset form ke blank
const openCreate = () => {
    form.clearErrors();           // bersihkan error lama
    form.reset();                 // kembalikan ke nilai awal useForm
    form.category = props.category; // set kategori aktif
    mode.value = 'create';        // mode create
    editId.value = null;          // tak ada id
    open.value = true;            // tampilkan modal
};

// Buka modal edit: isi form dari data entri
const openEdit = (p) => {
    form.clearErrors();                                  // bersihkan error lama
    form.category = p.category;                          // kategori entri
    form.account = p.account;                            // account entri
    form.endorse = p.endorse;                            // endorse entri
    form.outputs = (p.outputs || []).map((o) => o.id);   // id output relasi
    form.progressKey = p.progress;                       // progress entri
    form.payment_status = p.payment_status;              // payment entri
    form.tanggal_posting = p.tanggal_posting ? p.tanggal_posting.substring(0, 10) : ''; // ambil YYYY-MM-DD
    form.tanggal_payment = p.tanggal_payment ? p.tanggal_payment.substring(0, 10) : ''; // ambil YYYY-MM-DD
    form.amount_idr = p.amount_idr ?? '';                // nominal IDR
    form.amount_usd = p.amount_usd ?? '';                // nominal USD
    form.notes = p.notes ?? '';                          // notes
    mode.value = 'edit';                                 // mode edit
    editId.value = p.id;                                 // simpan id
    open.value = true;                                   // tampilkan modal
};

// Toggle checkbox output pada form
const toggleOutput = (id) => {
    const has = form.outputs.includes(id);                                          // sudah tercentang?
    form.outputs = has ? form.outputs.filter((x) => x !== id) : [...form.outputs, id]; // hapus/tambah id
};

// Submit form (create → post, edit → put), tutup modal bila sukses
const submit = () => {
    const done = { onSuccess: () => (open.value = false) };  // tutup modal setelah berhasil
    // petakan progressKey → progress saat kirim ke server
    form.transform(({ progressKey, ...rest }) => ({ ...rest, progress: progressKey }));
    if (mode.value === 'create') form.post('/pipelines', done); // buat entri baru
    else form.put('/pipelines/' + editId.value, done);          // perbarui entri
};

// Hapus entri dengan konfirmasi native
const destroy = (p) => {
    if (confirm('Yakin ingin menghapus "' + p.endorse + '"? Tindakan ini tidak bisa dibatalkan.')) { // konfirmasi
        router.delete('/pipelines/' + p.id);                 // kirim DELETE
    }
};

// Logout via POST Inertia
const logout = () => router.post('/logout');

// Format USD "1,234.56" (en-US, 2 desimal)
const usd = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <!-- Bungkus dengan Layout (judul tab "Pipeline") -->
    <Layout title="Pipeline">
        <!-- Top bar gradient brand -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="max-w-[1600px] px-6 py-5 flex items-center justify-between">
                <div>
                    <!-- Judul & subjudul -->
                    <h1 class="text-2xl font-bold tracking-tight">SYSTEM AI PRENEUR</h1>
                    <p class="text-brand-100 text-sm">Manajemen endorsement &amp; pembayaran</p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Board pipeline baru (dulu di strip tab, sekarang di header) -->
                    <button v-if="auth?.user?.canManage" @click="boardForm.reset(); boardOpen = true" title="Board pipeline baru"
                            class="bg-brand-800/40 hover:bg-brand-800/60 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
                        + Board
                    </button>
                    <!-- Tautan report PDF (buka tab baru) -->
                    <a :href="'/pipelines/report?category=' + category" target="_blank" rel="noreferrer"
                       class="bg-brand-800/40 hover:bg-brand-800/60 text-white text-sm font-semibold px-4 py-2.5 rounded-xl flex items-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V18a2 2 0 01-2 2z"/></svg>
                        Report PDF
                    </a>
                    <!-- Tombol buka modal tambah -->
                    <button @click="openCreate"
                            class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tambah Entri
                    </button>
                    <!-- Nama user + tombol logout -->
                    <div class="flex items-center gap-2 pl-2 ml-1 border-l border-white/20">
                        <span class="text-sm text-brand-100 hidden sm:inline">{{ auth?.user?.name }}</span>
                        <button @click="logout"
                                class="bg-brand-800/40 hover:bg-brand-800/60 text-white text-sm font-semibold px-3 py-2.5 rounded-xl transition flex items-center gap-1.5" title="Keluar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Keluar
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Area konten utama -->
        <div class="max-w-[1600px] px-6 py-6">

            <!-- Info kurs terkini -->
            <div class="flex items-center gap-2 mb-3 text-xs">
                <span class="inline-flex items-center gap-1.5 bg-white border border-brand-100 rounded-full px-3 py-1 shadow-sm">
                    <!-- Titik indikator: hijau bila kurs terkini, amber bila fallback -->
                    <span :class="'w-2 h-2 rounded-full ' + (summary.rate !== 16000 ? 'bg-emerald-500' : 'bg-amber-400')"></span>
                    Kurs {{ summary.rate !== 16000 ? 'terkini' : 'fallback' }}:
                    <strong class="text-brand-700">1 USD = {{ rp(summary.rate) }}</strong>
                </span>
            </div>

            <!-- Kartu ringkasan (omzet & pembayaran) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 mb-3">
                <!-- Total omzet gabungan (IDR) — sengaja paling kiri -->
                <div class="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                    <p class="text-xs text-brand-100 font-medium">Total Omzet (IDR)</p>
                    <p class="text-lg font-bold mt-1">{{ rp(summary.grand_idr) }}</p>
                    <p class="text-[10px] text-brand-200 mt-0.5">USD dikonversi otomatis</p>
                </div>
                <!-- Omzet IDR -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Omzet IDR</p>
                    <p class="text-lg font-bold text-brand-700 mt-1">{{ rp(summary.total_idr) }}</p>
                </div>
                <!-- Omzet USD -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Omzet USD</p>
                    <p class="text-lg font-bold text-brand-700 mt-1">$ {{ usd(summary.total_usd) }}</p>
                </div>
                <!-- Outstanding -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Outstanding (Belum+DP)</p>
                    <p class="text-lg font-bold text-red-600 mt-1">{{ summary.outstanding }} entri</p>
                </div>
                <!-- Lunas -->
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Lunas</p>
                    <p class="text-lg font-bold text-emerald-600 mt-1">{{ summary.lunas }} / {{ summary.total }}</p>
                </div>
            </div>

            <!-- Progress done: baris sendiri di bawah kartu ringkasan -->
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 mb-6">
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Progress Done</p>
                    <p class="text-lg font-bold text-brand-700 mt-1">{{ summary.done }} / {{ summary.total }}</p>
                </div>
            </div>

            <!-- Bar filter -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4 mb-5 flex flex-wrap gap-2 items-center text-sm">
                <!-- Input pencarian: terapkan saat Enter atau blur -->
                <input v-model="f.search" placeholder="Cari endorse / notes..."
                       @keydown.enter="applyFilters({ search: f.search })"
                       @blur="applyFilters({ search: f.search })"
                       class="border border-slate-200 rounded-xl px-3 py-2 w-56 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none" />
                <!-- Pilih board/kategori (pengganti strip tab) -->
                <select v-model="f.category" @change="applyFilters()"
                        class="border border-slate-200 rounded-xl px-3 py-2 font-semibold text-brand-700 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option v-for="(cv, ck) in categories" :key="ck" :value="ck">{{ cv }} ({{ counts[ck] }})</option>
                </select>
                <!-- Filter account -->
                <select v-model="f.account" @change="applyFilters()"
                        class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="">Semua Account</option>
                    <option v-for="(v, k) in accounts" :key="k" :value="k">{{ v }}</option>
                </select>
                <!-- Filter progress -->
                <select v-model="f.progress" @change="applyFilters()"
                        class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="">Semua Progress</option>
                    <option v-for="(v, k) in progresses" :key="k" :value="k">{{ v }}</option>
                </select>
                <!-- Filter payment -->
                <select v-model="f.payment_status" @change="applyFilters()"
                        class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="">Semua Payment</option>
                    <option v-for="(v, k) in payments" :key="k" :value="k">{{ v }}</option>
                </select>
                <!-- Filter output -->
                <select v-model="f.output" @change="applyFilters()"
                        class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="">Semua Output</option>
                    <option v-for="out in outputs" :key="out.id" :value="out.id">{{ out.name }}</option>
                </select>
                <!-- Reset filter → bersihkan filter tapi tetap di board yang sama -->
                <Link :href="'/pipelines?category=' + category" class="text-brand-600 hover:text-brand-800 px-2 font-medium">Reset</Link>
            </div>

            <!-- Tabel entri -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                            <th class="px-4 py-3 text-left">Account</th>
                            <th class="px-4 py-3 text-left">Endorse</th>
                            <th class="px-4 py-3 text-left">Output</th>
                            <th class="px-4 py-3 text-left">Progress</th>
                            <th class="px-4 py-3 text-left">Tgl Posting</th>
                            <th class="px-4 py-3 text-left">Tgl Payment</th>
                            <th class="px-4 py-3 text-left">Payment</th>
                            <th class="px-4 py-3 text-right">IDR</th>
                            <th class="px-4 py-3 text-right">USD</th>
                            <th class="px-4 py-3 text-left">Notes</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-50">
                        <!-- Pesan kosong bila tak ada entri -->
                        <tr v-if="pipelines.data.length === 0"><td colspan="11" class="px-4 py-10 text-center text-slate-400">Belum ada entri.</td></tr>
                        <!-- Baris data (hanya halaman aktif) -->
                        <tr v-else v-for="p in pipelines.data" :key="p.id" class="hover:bg-brand-50/60 transition">
                            <!-- Badge account -->
                            <td class="px-4 py-2.5">
                                <span :class="'inline-block ' + (ACCOUNT_COLORS[p.account] || 'bg-slate-200 text-slate-700') + ' text-xs font-semibold px-2.5 py-0.5 rounded-full'">
                                    {{ accounts[p.account] }}
                                </span>
                            </td>
                            <!-- Nama endorse -->
                            <td class="px-4 py-2.5 font-semibold text-slate-700">{{ p.endorse }}</td>
                            <!-- Daftar output -->
                            <td class="px-4 py-2.5">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="out in (p.outputs || [])" :key="out.id" class="text-xs px-2 py-0.5 rounded-full bg-brand-100 text-brand-700 border border-brand-200">{{ out.name }}</span>
                                </div>
                            </td>
                            <!-- Badge progress -->
                            <td class="px-4 py-2.5">
                                <span :class="'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (PROGRESS_COLORS[p.progress] || 'bg-slate-200 text-slate-700')">
                                    {{ progresses[p.progress] || p.progress }}
                                </span>
                            </td>
                            <!-- Tanggal posting -->
                            <td class="px-4 py-2.5 text-slate-500">{{ fmtDate(p.tanggal_posting) }}</td>
                            <!-- Tanggal payment -->
                            <td class="px-4 py-2.5 text-slate-500">{{ fmtDate(p.tanggal_payment) }}</td>
                            <!-- Badge payment -->
                            <td class="px-4 py-2.5">
                                <span :class="'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (PAYMENT_COLORS[p.payment_status] || 'bg-slate-200 text-slate-700')">
                                    {{ payments[p.payment_status] }}
                                </span>
                            </td>
                            <!-- Nominal IDR -->
                            <td class="px-4 py-2.5 text-right whitespace-nowrap font-medium">{{ p.amount_idr ? rp(p.amount_idr) : '—' }}</td>
                            <!-- Nominal USD -->
                            <td class="px-4 py-2.5 text-right whitespace-nowrap font-medium">{{ p.amount_usd ? '$' + usd(p.amount_usd) : '—' }}</td>
                            <!-- Notes -->
                            <td class="px-4 py-2.5 text-slate-500 max-w-[200px]">{{ p.notes || '—' }}</td>
                            <!-- Aksi edit & hapus -->
                            <td class="px-4 py-2.5 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click="openEdit(p)"
                                            class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z"/></svg>
                                        Edit
                                    </button>
                                    <button @click="destroy(p)"
                                            class="bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Jumlah entri ditampilkan -->
            <p class="text-xs text-slate-400 mt-3">{{ pipelines.length }} entri ditampilkan.</p>
        </div>

        <!-- Modal Tambah/Edit -->
        <div v-if="open" class="fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex items-start justify-center overflow-y-auto py-10 z-50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 border-t-4 border-brand-600">
                <!-- Header modal -->
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-bold text-brand-800">{{ mode === 'create' ? '+ Tambah Entri' : 'Edit Entri' }}</h2>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                </div>
                <!-- Form entri -->
                <form @submit.prevent="submit" class="grid grid-cols-2 gap-4 text-sm">
                    <!-- Kategori -->
                    <label class="block font-medium text-slate-600">Kategori
                        <select v-model="form.category" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                            <option v-for="(v, k) in categories" :key="k" :value="k">{{ v }}</option>
                        </select>
                        <span v-if="form.errors.category" class="text-xs text-red-600">{{ form.errors.category }}</span>
                    </label>
                    <!-- Account -->
                    <label class="block font-medium text-slate-600">Account
                        <select v-model="form.account" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                            <option v-for="(v, k) in accounts" :key="k" :value="k">{{ v }}</option>
                        </select>
                        <span v-if="form.errors.account" class="text-xs text-red-600">{{ form.errors.account }}</span>
                    </label>
                    <!-- Endorse / produk -->
                    <label class="block col-span-2 font-medium text-slate-600">Endorse / Produk
                        <input v-model="form.endorse" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.errors.endorse" class="text-xs text-red-600">{{ form.errors.endorse }}</span>
                    </label>

                    <!-- Output (checkbox multi) -->
                    <label class="block col-span-2 font-medium text-slate-600">Output
                        <div class="mt-2 flex flex-wrap gap-3">
                            <label v-for="out in outputs" :key="out.id" class="inline-flex items-center gap-1.5 bg-brand-50 border border-brand-100 rounded-lg px-3 py-1.5 cursor-pointer">
                                <input type="checkbox" :value="out.id" class="accent-brand-600"
                                       :checked="form.outputs.includes(out.id)" @change="toggleOutput(out.id)" /> {{ out.name }}
                            </label>
                        </div>
                        <span v-if="form.errors.outputs" class="text-xs text-red-600">{{ form.errors.outputs }}</span>
                    </label>

                    <!-- Progress -->
                    <label class="block font-medium text-slate-600">Progress
                        <select v-model="form.progressKey" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                            <option v-for="(v, k) in progresses" :key="k" :value="k">{{ v }}</option>
                        </select>
                        <span v-if="form.errors.progress" class="text-xs text-red-600">{{ form.errors.progress }}</span>
                    </label>
                    <!-- Payment status -->
                    <label class="block font-medium text-slate-600">Payment Status
                        <select v-model="form.payment_status" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                            <option v-for="(v, k) in payments" :key="k" :value="k">{{ v }}</option>
                        </select>
                        <span v-if="form.errors.payment_status" class="text-xs text-red-600">{{ form.errors.payment_status }}</span>
                    </label>

                    <!-- Tanggal posting -->
                    <label class="block font-medium text-slate-600">Tanggal Posting
                        <input type="date" v-model="form.tanggal_posting" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.errors.tanggal_posting" class="text-xs text-red-600">{{ form.errors.tanggal_posting }}</span>
                    </label>
                    <!-- Tanggal payment -->
                    <label class="block font-medium text-slate-600">Tanggal Payment
                        <input type="date" v-model="form.tanggal_payment" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.errors.tanggal_payment" class="text-xs text-red-600">{{ form.errors.tanggal_payment }}</span>
                    </label>

                    <!-- Jumlah IDR + estimasi USD -->
                    <label class="block font-medium text-slate-600">Jumlah IDR
                        <input type="number" step="0.01" v-model="form.amount_idr" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.amount_idr > 0" class="text-[11px] text-brand-600">{{ '≈ $ ' + usd(form.amount_idr / rate) }}</span>
                        <span v-if="form.errors.amount_idr" class="text-xs text-red-600 block">{{ form.errors.amount_idr }}</span>
                    </label>
                    <!-- Jumlah USD + estimasi IDR -->
                    <label class="block font-medium text-slate-600">Jumlah USD
                        <input type="number" step="0.01" v-model="form.amount_usd" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.amount_usd > 0" class="text-[11px] text-brand-600">{{ '≈ Rp ' + Math.round(form.amount_usd * rate).toLocaleString('id-ID') }}</span>
                        <span v-if="form.errors.amount_usd" class="text-xs text-red-600 block">{{ form.errors.amount_usd }}</span>
                    </label>

                    <!-- Notes panjang -->
                    <label class="block col-span-2 font-medium text-slate-600">Notes
                        <textarea v-model="form.notes" rows="2" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                        <span v-if="form.errors.notes" class="text-xs text-red-600">{{ form.errors.notes }}</span>
                    </label>

                    <!-- Aksi modal -->
                    <div class="col-span-2 flex justify-end gap-2 mt-2">
                        <button type="button" @click="open = false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal buat board pipeline -->
        <ModalWrap v-if="boardOpen" width="max-w-sm" @close="boardOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Board Pipeline Baru</h2>
            <form @submit.prevent="submitBoard" class="space-y-3 text-sm">
                <label class="block font-medium text-slate-600">Nama board
                    <input v-model="boardForm.name" required autofocus placeholder="mis. Endorse" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="block font-medium text-slate-600">Section / Grup (opsional)
                    <input v-model="boardForm.section" placeholder="mis. Q1 2026" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="boardOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="boardForm.processing" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold disabled:opacity-60">Simpan</button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

<script setup>
// Halaman Order: ringkasan + filter + tabel (10/halaman) + modal CRUD tambah/edit.
import { ref, reactive, computed } from 'vue';                    // state lokal & turunan reaktif
import { Link, useForm, router, usePage } from '@inertiajs/vue3'; // navigasi, form, aksi Inertia
import Layout from '../../Layout.vue';                            // kerangka (sidebar + toast)
import ModalWrap from '../../ModalWrap.vue';                      // pembungkus modal

// Props dari OrderController@index
const props = defineProps({
    orders: Object,          // paginator Laravel: { data, links, total, from, to, last_page }
    filters: Object,         // nilai filter aktif dari server
    summary: Object,         // { total, totalIdr, totalUsd, grandIdr, dp }
    tipeOrder: Object,       // peta key→label tipe order
    accounts: Object,        // peta key→label akun (fk / ai_preneur)
    tipePembayaran: Object,  // peta key→label tipe pembayaran
    kotaList: Array,         // saran kota (514 kab/kota + Singapura/Australia/Miri City)
    outputList: { type: Array, default: () => [] },   // pilihan checkbox Output
    rate: { type: Number, default: 0 },  // kurs USD→IDR utk total gabungan per baris
});

// User login dari shared props (untuk gating tombol CRUD)
const page = usePage();
const auth = computed(() => page.props.auth);

// Warna badge tipe order. Kelas ditulis literal di sini (bukan dikirim server)
// supaya terbaca scanner Tailwind — jangan pindahkan ke PHP.
const TIPE_COLORS = {
    coaching_1on1: 'bg-brand-600 text-white',       // coaching 1-on-1 → brand
    coaching_perusahaan: 'bg-indigo-500 text-white',// coaching perusahaan → indigo
    endorse: 'bg-emerald-600 text-white',           // endorse → hijau
    speaker: 'bg-amber-600 text-white',             // speaker → amber
    agency: 'bg-rose-600 text-white',               // agency → rose
};

// Format Rupiah "Rp 1.234.567"
const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
// Format USD "$1,250"
const usd = (n) => '$' + Number(n || 0).toLocaleString('en-US');
// Total satu order dlm IDR = nominal IDR + nominal USD × kurs.
// Turunan, bukan kolom — kalau disimpan, nilainya basi begitu kurs berubah.
const totalOrder = (o) => Number(o.total_idr || 0) + Number(o.total_usd || 0) * props.rate;
// Format tanggal "15 Jul 2026"; em-dash bila kosong
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

// ---- Filter ----
const f = reactive({
    tipe_order: props.filters.tipe_order || '',
    account: props.filters.account || '',
    tipe_pembayaran: props.filters.tipe_pembayaran || '',
    date_from: props.filters.date_from || '',   // batas awal deadline
    date_to: props.filters.date_to || '',       // batas akhir deadline
    search: props.filters.search || '',
});

// Terapkan filter → GET Inertia. Tanpa param `page` agar selalu balik ke halaman 1.
const applyFilters = (next = {}) => {
    Object.assign(f, next);
    router.get('/orders', { ...f }, { preserveState: true, preserveScroll: true, replace: true });
};

// ---- Modal tambah/edit ----
const open = ref(false);        // modal terbuka?
const mode = ref('create');     // 'create' | 'edit'
const editId = ref(null);       // id order yang diedit
// ref tiap <input type="file"> — perlu dibersihkan manual saat reset
// (form.reset() tak menghapus nama file yang tampil di elemennya)
const fileInput = ref(null);       // bukti bayar
const invoiceInput = ref(null);    // invoice perusahaan

// Form Inertia; field top-level (bukan form.data.x) sesuai konvensi repo
const form = useForm({
    tipe_order: 'endorse',
    account: 'fk',              // order ini masuk akun mana
    tanggal_deadline: '',
    nama_customer: '',
    telepon: '',
    email: '',
    kota: '',                   // bebas diketik; kotaList cuma saran datalist
    alamat: '',
    tipe_pembayaran: 'full',
    tanggal_bayar: '',
    total_idr: '',
    total_usd: '',
    outputs: [],                // id Output tercentang (pivot order_output)
    bukti_bayar: null,          // File object; null = tak ada file baru
    invoice: null,              // invoice dari perusahaan
});

// Centang/hapus centang satu output. Ganti array (bukan push/splice) supaya
// Inertia melihat perubahannya.
const toggleOutput = (id) => {
    form.outputs = form.outputs.includes(id) ? form.outputs.filter((x) => x !== id) : [...form.outputs, id];
};

// Kosongkan form + kedua input file
const resetForm = () => {
    form.reset();
    form.clearErrors();
    if (fileInput.value) fileInput.value.value = '';
    if (invoiceInput.value) invoiceInput.value.value = '';
};

// Buka modal tambah: form kembali blank
const openCreate = () => {
    resetForm();
    mode.value = 'create';
    editId.value = null;
    open.value = true;
};

// Buka modal edit: isi form dari baris tabel
const openEdit = (o) => {
    resetForm();
    form.tipe_order = o.tipe_order;
    form.account = o.account ?? 'fk';
    // tanggal dari server berformat ISO → potong ke YYYY-MM-DD agar cocok <input type="date">
    form.tanggal_deadline = o.tanggal_deadline ? o.tanggal_deadline.substring(0, 10) : '';
    form.nama_customer = o.nama_customer;
    form.telepon = o.telepon ?? '';
    form.email = o.email ?? '';
    form.kota = o.kota ?? '';
    form.alamat = o.alamat ?? '';
    form.tipe_pembayaran = o.tipe_pembayaran;
    form.tanggal_bayar = o.tanggal_bayar ? o.tanggal_bayar.substring(0, 10) : '';
    form.total_idr = o.total_idr ?? '';
    form.total_usd = o.total_usd ?? '';
    // relasi outputs di-eager-load controller → ambil id-nya. Number(): id dari JSON
    // bisa string, sementara :value checkbox membandingkan dgn id numerik prop.
    form.outputs = Array.isArray(o.outputs) ? o.outputs.map((x) => Number(x.id)) : [];
    form.bukti_bayar = null;    // biarkan null → file lama dipertahankan server
    form.invoice = null;
    mode.value = 'edit';
    editId.value = o.id;
    open.value = true;
};

// Submit. Ada upload file → forceFormData wajib (kirim multipart, bukan JSON).
// Edit tetap pakai POST + _method=put: multipart tak bisa dikirim lewat PUT.
const submit = () => {
    const done = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { open.value = false; resetForm(); },
    };

    // transform() menempel di instance form dan BERTAHAN antar submit, jadi wajib
    // diset di kedua cabang. Kalau create tak menyetel ulang, `_method: 'put'` sisa
    // dari edit sebelumnya ikut terkirim → POST /orders di-spoof jadi PUT → 405.
    if (mode.value === 'create') {
        form.transform((d) => d).post('/orders', done);
    } else {
        form.transform((d) => ({ ...d, _method: 'put' })).post('/orders/' + editId.value, done);
    }
};

// Hapus dengan konfirmasi native
const destroy = (o) => {
    if (confirm('Yakin hapus order "' + o.nama_customer + '"? Tindakan ini tidak bisa dibatalkan.')) {
        router.delete('/orders/' + o.id, { preserveScroll: true });
    }
};
</script>

<template>
    <Layout title="Order">
        <!-- Top bar gradient brand -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="max-w-[1600px] px-6 py-5 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">ORDER</h1>
                    <p class="text-brand-100 text-sm">Pesanan customer &amp; pembayaran</p>
                </div>
                <!-- Tombol tambah hanya untuk yang boleh mengelola -->
                <button v-if="auth?.user?.canManage" @click="openCreate"
                        class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Buat Pesanan
                </button>
            </div>
        </header>

        <div class="max-w-[1600px] px-6 py-6">

            <!-- Kartu ringkasan. Total Pembayaran = omzet IDR + omzet USD dikonversi kurs,
                 selalu dipajang dalam IDR. Omzet IDR/USD dipisah agar angka aslinya tetap kebaca. -->
            <div class="grid grid-cols-2 xl:grid-cols-5 gap-3 mb-6">
                <div class="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                    <p class="text-xs text-brand-100 font-medium">Total Pembayaran</p>
                    <p class="text-lg font-bold mt-1">{{ rp(summary.grandIdr) }}</p>
                    <p class="text-[10px] text-brand-100 mt-0.5">IDR + USD @ {{ rp(rate) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Omzet IDR</p>
                    <p class="text-lg font-bold text-slate-700 mt-1">{{ rp(summary.totalIdr) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Omzet USD</p>
                    <p class="text-lg font-bold text-slate-700 mt-1">{{ usd(summary.totalUsd) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Total Order</p>
                    <p class="text-lg font-bold text-brand-700 mt-1">{{ summary.total }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Masih DP</p>
                    <p class="text-lg font-bold text-amber-600 mt-1">{{ summary.dp }}</p>
                </div>
            </div>

            <!-- Bar filter -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4 mb-5 flex flex-wrap gap-2 items-center text-sm">
                <!-- Pencarian: terapkan saat Enter atau blur -->
                <input v-model="f.search" placeholder="Cari nama / telepon / email / kota..."
                       @keydown.enter="applyFilters({ search: f.search })"
                       @blur="applyFilters({ search: f.search })"
                       class="border border-slate-200 rounded-xl px-3 py-2 w-56 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none" />
                <select v-model="f.tipe_order" @change="applyFilters()" class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="">Semua Tipe Order</option>
                    <option v-for="(v, k) in tipeOrder" :key="k" :value="k">{{ v }}</option>
                </select>
                <select v-model="f.account" @change="applyFilters()" class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="">Semua Akun</option>
                    <option v-for="(v, k) in accounts" :key="k" :value="k">{{ v }}</option>
                </select>
                <select v-model="f.tipe_pembayaran" @change="applyFilters()" class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    <option value="">Semua Pembayaran</option>
                    <option v-for="(v, k) in tipePembayaran" :key="k" :value="k">{{ v }}</option>
                </select>
                <!-- Rentang deadline: kedua sisi opsional -->
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-slate-500 font-medium">Deadline</span>
                    <input v-model="f.date_from" type="date" @change="applyFilters()" title="Deadline dari"
                           class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span class="text-slate-400">–</span>
                    <input v-model="f.date_to" type="date" @change="applyFilters()" title="Deadline sampai"
                           class="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </div>
                <Link href="/orders" class="text-brand-600 hover:text-brand-800 px-2 font-medium">Reset</Link>
            </div>

            <!-- Tabel order -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Kontak</th>
                            <th class="px-4 py-3 text-left">Tipe Order</th>
                            <th class="px-4 py-3 text-left">Akun</th>
                            <th class="px-4 py-3 text-left">Deadline</th>
                            <th class="px-4 py-3 text-left">Pembayaran</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-center">Berkas</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-50">
                        <!-- Kosong -->
                        <tr v-if="orders.data.length === 0"><td colspan="9" class="px-4 py-10 text-center text-slate-400">Belum ada order.</td></tr>
                        <!-- Baris data (hanya halaman aktif) -->
                        <tr v-else v-for="o in orders.data" :key="o.id" class="hover:bg-brand-50/60 transition">
                            <!-- Nama + kota -->
                            <td class="px-4 py-2.5">
                                <p class="font-semibold text-slate-700">{{ o.nama_customer }}</p>
                                <p class="text-xs text-slate-400">{{ o.kota || '—' }}</p>
                            </td>
                            <!-- Telepon + email -->
                            <td class="px-4 py-2.5 text-slate-500">
                                <p>{{ o.telepon || '—' }}</p>
                                <p v-if="o.email" class="text-xs text-slate-400 truncate max-w-[180px]" :title="o.email">{{ o.email }}</p>
                            </td>
                            <!-- Badge tipe order -->
                            <td class="px-4 py-2.5">
                                <span :class="'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (TIPE_COLORS[o.tipe_order] || 'bg-slate-200 text-slate-700')">
                                    {{ tipeOrder[o.tipe_order] }}
                                </span>
                            </td>
                            <!-- Akun tujuan order -->
                            <td class="px-4 py-2.5">
                                <span :class="'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (o.account === 'fk' ? 'bg-brand-600 text-white' : 'bg-slate-500 text-white')">
                                    {{ accounts[o.account] || o.account }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap">{{ fmtDate(o.tanggal_deadline) }}</td>
                            <!-- Tipe pembayaran + tanggal bayar -->
                            <td class="px-4 py-2.5">
                                <span :class="'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (o.tipe_pembayaran === 'full' ? 'bg-emerald-600 text-white' : 'bg-amber-400 text-amber-900')">
                                    {{ tipePembayaran[o.tipe_pembayaran] }}
                                </span>
                                <p class="text-xs text-slate-400 mt-0.5">{{ fmtDate(o.tanggal_bayar) }}</p>
                            </td>
                            <!-- Total = IDR + USD×kurs, selalu tampil IDR. Rincian di bawahnya
                                 supaya angka asli tiap mata uang tetap kebaca. -->
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                <p class="font-semibold text-slate-700">{{ rp(totalOrder(o)) }}</p>
                                <p v-if="Number(o.total_usd) > 0" class="text-[10px] text-slate-400">
                                    {{ rp(o.total_idr) }} + {{ usd(o.total_usd) }}
                                </p>
                            </td>
                            <!-- Berkas: bukti bayar customer + invoice perusahaan, buka di tab baru -->
                            <td class="px-4 py-2.5 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2 text-xs font-semibold">
                                    <a v-if="o.bukti_bayar" :href="'/storage/' + o.bukti_bayar" target="_blank" rel="noreferrer"
                                       class="text-brand-600 hover:text-brand-800 underline">Bukti</a>
                                    <a v-if="o.invoice" :href="'/storage/' + o.invoice" target="_blank" rel="noreferrer"
                                       class="text-indigo-600 hover:text-indigo-800 underline">Invoice</a>
                                    <span v-if="!o.bukti_bayar && !o.invoice" class="text-slate-300 font-normal">—</span>
                                </div>
                            </td>
                            <!-- Aksi -->
                            <td class="px-4 py-2.5 text-center whitespace-nowrap">
                                <div v-if="auth?.user?.canManage" class="flex items-center justify-center gap-1.5">
                                    <button @click="openEdit(o)" class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">Edit</button>
                                    <button @click="destroy(o)" class="bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                                </div>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Info rentang + navigasi halaman (10 baris/halaman) -->
            <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
                <p class="text-xs text-slate-400">
                    Menampilkan {{ orders.from || 0 }}–{{ orders.to || 0 }} dari {{ orders.total }} order.
                </p>
                <!-- url null = tombol mati (Prev di hal. 1 / Next di hal. akhir) -->
                <div v-if="orders.last_page > 1" class="flex flex-wrap gap-1">
                    <template v-for="(l, i) in orders.links" :key="i">
                        <Link v-if="l.url" :href="l.url" preserve-scroll
                              :class="'px-3 py-1.5 text-xs font-semibold rounded-lg border transition ' + (l.active ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white border-brand-100 text-brand-700 hover:bg-brand-50')"
                              v-html="l.label" />
                        <span v-else class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-100 text-slate-300" v-html="l.label" />
                    </template>
                </div>
            </div>
        </div>

        <!-- ===== Modal tambah/edit ===== -->
        <ModalWrap v-if="open" width="max-w-3xl" align="items-start" @close="open = false">
            <h2 class="text-lg font-bold text-slate-800 mb-4">{{ mode === 'create' ? 'Buat Pesanan' : 'Edit Pesanan' }}</h2>

            <form @submit.prevent="submit" class="space-y-5">
                <!-- ---- Seksi: Info Order ---- -->
                <div>
                    <h3 class="text-sm font-bold text-brand-700 border-b-2 border-brand-600 pb-1 mb-3">Info Order</h3>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <!-- Tipe order: coaching/endorse/speaker/agency -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Tipe Order <span class="text-red-500">*</span></label>
                            <select v-model="form.tipe_order" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                <option v-for="(v, k) in tipeOrder" :key="k" :value="k">{{ v }}</option>
                            </select>
                            <span v-if="form.errors.tipe_order" class="text-xs text-red-600">{{ form.errors.tipe_order }}</span>
                        </div>
                        <!-- Akun: order ini milik FK atau AI Preneur -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Masuk ke Akun <span class="text-red-500">*</span></label>
                            <select v-model="form.account" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                <option v-for="(v, k) in accounts" :key="k" :value="k">{{ v }}</option>
                            </select>
                            <span v-if="form.errors.account" class="text-xs text-red-600">{{ form.errors.account }}</span>
                        </div>
                        <!-- Tanggal deadline -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Tanggal Deadline <span class="text-red-500">*</span></label>
                            <input v-model="form.tanggal_deadline" type="date" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <span v-if="form.errors.tanggal_deadline" class="text-xs text-red-600">{{ form.errors.tanggal_deadline }}</span>
                        </div>
                    </div>
                </div>

                <!-- ---- Seksi: Customer ---- -->
                <div>
                    <h3 class="text-sm font-bold text-brand-700 border-b-2 border-brand-600 pb-1 mb-3">Customer</h3>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <!-- Nama (wajib) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Nama Customer / Perusahaan <span class="text-red-500">*</span></label>
                            <input v-model="form.nama_customer" placeholder="Nama orang / perusahaan" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <span v-if="form.errors.nama_customer" class="text-xs text-red-600">{{ form.errors.nama_customer }}</span>
                        </div>
                        <!-- Telepon -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Telepon <span class="text-red-500">*</span></label>
                            <input v-model="form.telepon" placeholder="Telepon" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <span v-if="form.errors.telepon" class="text-xs text-red-600">{{ form.errors.telepon }}</span>
                        </div>
                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Email</label>
                            <input v-model="form.email" type="email" placeholder="nama@email.com" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span>
                        </div>
                        <!-- Kota/kabupaten: <datalist> = ketik untuk cari, tanpa library.
                             Daftar wilayah cuma SARAN — kota di luar dataset boleh diketik manual. -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Kota / Kabupaten <span class="text-red-500">*</span></label>
                            <input v-model="form.kota" list="kota-list" placeholder="Ketik untuk cari / isi manual..."
                                   class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <datalist id="kota-list">
                                <option v-for="k in kotaList" :key="k" :value="k" />
                            </datalist>
                            <span v-if="form.errors.kota" class="text-xs text-red-600">{{ form.errors.kota }}</span>
                        </div>
                        <!-- Alamat -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Alamat</label>
                            <textarea v-model="form.alamat" rows="2" placeholder="Alamat" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                            <span v-if="form.errors.alamat" class="text-xs text-red-600">{{ form.errors.alamat }}</span>
                        </div>
                    </div>
                </div>

                <!-- ---- Seksi: Pembayaran ---- -->
                <div>
                    <h3 class="text-sm font-bold text-brand-700 border-b-2 border-brand-600 pb-1 mb-3">Pembayaran</h3>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <!-- Tipe pembayaran: full / dp -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Tipe Pembayaran <span class="text-red-500">*</span></label>
                            <select v-model="form.tipe_pembayaran" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                <option v-for="(v, k) in tipePembayaran" :key="k" :value="k">{{ v }}</option>
                            </select>
                            <span v-if="form.errors.tipe_pembayaran" class="text-xs text-red-600">{{ form.errors.tipe_pembayaran }}</span>
                        </div>
                        <!-- Tanggal bayar -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Tanggal Bayar</label>
                            <input v-model="form.tanggal_bayar" type="date" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <span v-if="form.errors.tanggal_bayar" class="text-xs text-red-600">{{ form.errors.tanggal_bayar }}</span>
                        </div>
                        <!-- Nominal IDR -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Nilai Order (IDR) <span class="text-red-500">*</span></label>
                            <input v-model="form.total_idr" type="number" min="0" step="1000" placeholder="0"
                                   class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ rp(form.total_idr) }}</p>
                            <span v-if="form.errors.total_idr" class="text-xs text-red-600">{{ form.errors.total_idr }}</span>
                        </div>
                        <!-- Nominal USD -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Nilai Order (USD)</label>
                            <input v-model="form.total_usd" type="number" min="0" step="0.01" placeholder="0"
                                   class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ usd(form.total_usd) }}</p>
                            <span v-if="form.errors.total_usd" class="text-xs text-red-600">{{ form.errors.total_usd }}</span>
                        </div>
                        <!-- Total gabungan: turunan, tak disimpan. Isi dua-duanya kalau order campuran. -->
                        <div class="sm:col-span-2 bg-brand-50 border border-brand-100 rounded-xl px-3 py-2 flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-600">Total Pembayaran <span class="font-normal text-slate-400">(IDR + USD @ {{ rp(rate) }})</span></span>
                            <span class="text-base font-bold text-brand-700">{{ rp(Number(form.total_idr || 0) + Number(form.total_usd || 0) * rate) }}</span>
                        </div>
                        <!-- Output: pilihannya = isi tabel `outputs` (satu sumber dgn kartu
                             Sales/Kanban), jadi tambah output cukup lewat migrasi — tak ada
                             daftar kedua di sini. -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Output</label>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="out in outputList" :key="out.id"
                                       class="inline-flex items-center gap-1.5 bg-brand-50 border border-brand-100 rounded-lg px-3 py-1.5 text-xs cursor-pointer hover:bg-brand-100 transition">
                                    <input type="checkbox" :checked="form.outputs.includes(out.id)" @change="toggleOutput(out.id)" class="accent-brand-600" />
                                    {{ out.name }}
                                </label>
                                <p v-if="!outputList.length" class="text-xs text-slate-400">Belum ada pilihan output.</p>
                            </div>
                            <span v-if="form.errors.outputs" class="text-xs text-red-600">{{ form.errors.outputs }}</span>
                        </div>
                        <!-- Bukti bayar: file baru menimpa yang lama; dikosongkan = file lama dipertahankan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Bukti Transfer <span class="font-normal text-slate-400">(dari customer)</span></label>
                            <input ref="fileInput" type="file" accept=".jpg,.jpeg,.png,.pdf"
                                   @change="form.bukti_bayar = $event.target.files[0]"
                                   class="mt-1 w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold hover:file:bg-brand-100" />
                            <p class="text-[10px] text-slate-400 mt-0.5">JPG/PNG/PDF, maks 2MB. Kosongkan bila tak ingin mengganti.</p>
                            <span v-if="form.errors.bukti_bayar" class="text-xs text-red-600">{{ form.errors.bukti_bayar }}</span>
                        </div>
                        <!-- Invoice perusahaan: berkas terpisah dari bukti bayar customer -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Invoice <span class="font-normal text-slate-400">(kita terbitkan ke customer)</span></label>
                            <input ref="invoiceInput" type="file" accept=".jpg,.jpeg,.png,.pdf"
                                   @change="form.invoice = $event.target.files[0]"
                                   class="mt-1 w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-semibold hover:file:bg-indigo-100" />
                            <p class="text-[10px] text-slate-400 mt-0.5">JPG/PNG/PDF, maks 5MB. Kosongkan bila tak ingin mengganti.</p>
                            <span v-if="form.errors.invoice" class="text-xs text-red-600">{{ form.errors.invoice }}</span>
                        </div>
                    </div>
                </div>

                <!-- Aksi modal -->
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="open = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="form.processing"
                            class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold disabled:opacity-50">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

<script setup>
// Halaman Pembukuan: chart (read-only) + CRUD transaksi & inventaris (super_admin/it).
import { ref } from 'vue'; // state modal/edit
import { useForm, router } from '@inertiajs/vue3'; // form Inertia + aksi hapus
import Layout from '../Layout.vue'; // kerangka + sidebar + toast
import Pembukuan from '../scripts/components/Pembukuan.vue'; // komponen chart (prop `data`)
import ModalWrap from '../ModalWrap.vue'; // pembungkus modal
import { rp } from '../scripts/lib/format'; // format Rupiah

// Props dari controller
const props = defineProps({
    payload: Object, // data chart/rekap
    transactions: Array, // daftar transaksi mentah
    inventories: Array, // daftar inventaris mentah
    types: Object, // peta pemasukan/pengeluaran
    categories: { type: Array, default: () => [] }, // daftar kategori transaksi
});
const { payload, transactions, inventories, types, categories } = props;
const asset = (path) => (path ? '/storage/' + path : null);
const OTHER_CATEGORY_VALUE = '__lainnya__';
const OTHER_CATEGORY_LABEL = 'Lainnya';

const normalizeCategoryValue = (value) => (typeof value === 'string' ? value.trim() : '');

const availableCategories = ref(
    Array.from(
        new Set(
            [
                ...categories,
                ...transactions
                    .map((t) => normalizeCategoryValue(t.category))
                    .filter((cat) => cat && cat !== OTHER_CATEGORY_VALUE && cat !== OTHER_CATEGORY_LABEL),
            ]
                .filter((cat) => normalizeCategoryValue(cat) !== OTHER_CATEGORY_LABEL)
                .filter((cat) => normalizeCategoryValue(cat) !== OTHER_CATEGORY_VALUE),
        ),
    ),
);

const ensureCategoryInOptions = (value) => {
    const category = normalizeCategoryValue(value);
    if (!category || category === OTHER_CATEGORY_VALUE || category === OTHER_CATEGORY_LABEL) {
        return;
    }

    if (!availableCategories.value.includes(category)) {
        availableCategories.value.push(category);
    }
};

// ---- Modal Transaksi ----
const txOpen = ref(false);
const txEditId = ref(null);
const txBuktiInput = ref(null);
const txForm = useForm({
    type: 'pemasukan',
    category: '',
    category_other: '',
    description: '',
    amount_idr: '',
    date: '',
    bukti: null,
});
const setTxCategory = (value) => {
    const normalized = normalizeCategoryValue(value);

    if (!normalized) {
        txForm.category = '';
        txForm.category_other = '';
        return;
    }

    if (normalized === OTHER_CATEGORY_LABEL) {
        txForm.category = OTHER_CATEGORY_VALUE;
        txForm.category_other = '';
        return;
    }

    if (availableCategories.value.includes(normalized)) {
        txForm.category = normalized;
        txForm.category_other = '';
        return;
    }

    txForm.category = OTHER_CATEGORY_VALUE;
    txForm.category_other = normalized;
};
const openTxCreate = () => {
    txEditId.value = null;
    txForm.reset();
    txForm.category_other = '';
    txForm.clearErrors();
    if (txBuktiInput.value) txBuktiInput.value.value = '';
    txOpen.value = true;
};
const openTxEdit = (t) => {
    txEditId.value = t.id;
    txForm.type = t.type;
    setTxCategory(t.category);
    txForm.description = t.description ?? '';
    txForm.amount_idr = t.amount_idr;
    txForm.date = t.date;
    txForm.bukti = null;
    txForm.clearErrors();
    if (txBuktiInput.value) txBuktiInput.value.value = '';
    txOpen.value = true;
};
const submitTx = () => {
    const done = (category = '') => ({
        forceFormData: true,
        onSuccess: () => {
            ensureCategoryInOptions(category);
            txOpen.value = false;
            txForm.reset();
        },
        preserveScroll: true,
    });

    const txPayload = (d, method = null) => {
        const normalized = { ...d };
        if (d.category === OTHER_CATEGORY_VALUE) {
            normalized.category = (d.category_other ?? '').trim();
        }
        normalized.category = (normalized.category ?? '').trim();
        delete normalized.category_other;

        if (method) {
            normalized._method = method;
        }

        return normalized;
    };

    if (txEditId.value) {
        const normalized = txPayload(txForm.data(), 'put');
        if (!normalized.category) {
            txForm.setError('category', 'Kategori wajib diisi.');
            return;
        }
        txForm.transform(() => normalized).post('/transactions/' + txEditId.value, done(normalized.category));
    } else {
        const normalized = txPayload(txForm.data());
        if (!normalized.category) {
            txForm.setError('category', 'Kategori wajib diisi.');
            return;
        }
        txForm.transform(() => normalized).post('/transactions', done(normalized.category));
    }
};
const delTx = (t) => {
    if (confirm('Hapus transaksi ini?')) router.delete('/transactions/' + t.id, { preserveScroll: true });
};

// ---- OCR struk: foto struk → AI baca → prefill form (user tetap meninjau lalu simpan) ----
const ocrLoading = ref(false); // true selama AI membaca gambar
const ocrError = ref(''); // pesan error OCR untuk ditampilkan di modal
const scanStruk = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    ocrError.value = '';
    ocrLoading.value = true;
    txForm.bukti = file; // gambar yang discan sekalian dipakai sebagai bukti transaksi
    try {
        const fd = new FormData();
        fd.append('bukti', file);
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const res = await fetch('/transactions/ocr', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body: fd,
        });
        const data = await res.json();
        if (data.ok && data.data) {
            const d = data.data; // isi field yang berhasil dibaca; biarkan yang kosong
            if (d.type) txForm.type = d.type;
            if (d.category) setTxCategory(d.category);
            if (d.amount_idr) txForm.amount_idr = d.amount_idr;
            if (d.date) txForm.date = d.date;
            if (d.description) txForm.description = d.description;
        } else {
            ocrError.value = data.error || 'Gagal membaca struk.';
        }
    } catch (err) {
        ocrError.value = 'Gagal membaca struk (jaringan/AI tidak merespons).';
    } finally {
        ocrLoading.value = false;
        e.target.value = ''; // reset input agar file sama bisa dipilih lagi
    }
};

// ---- Modal Inventaris ----
const invOpen = ref(false);
const invEditId = ref(null);
const invForm = useForm({ name: '', qty: '', unit_value_idr: '', month: '' });
const openInvCreate = () => {
    invEditId.value = null;
    invForm.reset();
    invForm.clearErrors();
    invOpen.value = true;
};
const openInvEdit = (i) => {
    invEditId.value = i.id;
    invForm.name = i.name;
    invForm.qty = i.qty;
    invForm.unit_value_idr = i.unit_value_idr;
    invForm.month = i.month;
    invForm.clearErrors();
    invOpen.value = true;
};
// ---- OCR inventaris: foto barang/nota → AI baca → prefill form (user tetap meninjau) ----
const invOcrLoading = ref(false);
const invOcrError = ref('');
const scanInventaris = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    invOcrError.value = '';
    invOcrLoading.value = true;
    try {
        const fd = new FormData();
        fd.append('gambar', file);
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const res = await fetch('/inventories/ocr', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body: fd,
        });
        const data = await res.json();
        if (data.ok && data.data) {
            const d = data.data; // isi field yang berhasil dibaca; biarkan yang kosong
            if (d.name) invForm.name = d.name;
            if (d.qty) invForm.qty = d.qty;
            if (d.unit_value_idr) invForm.unit_value_idr = d.unit_value_idr;
            if (d.month) invForm.month = d.month;
        } else {
            invOcrError.value = data.error || 'Gagal membaca gambar.';
        }
    } catch {
        invOcrError.value = 'Gagal membaca gambar (jaringan/AI tidak merespons).';
    } finally {
        invOcrLoading.value = false;
        e.target.value = ''; // reset agar file yang sama bisa dipilih ulang
    }
};
const submitInv = () => {
    const done = {
        onSuccess: () => {
            invOpen.value = false;
            invForm.reset();
        },
        preserveScroll: true,
    };
    // input type=month → 'YYYY-MM'; server minta date → tambahkan '-01'
    invForm.transform((d) => ({ ...d, month: d.month && d.month.length === 7 ? d.month + '-01' : d.month }));
    if (invEditId.value)
        invForm.put('/inventories/' + invEditId.value, done); // update
    else invForm.post('/inventories', done); // create
};
const delInv = (i) => {
    if (confirm('Hapus inventaris ini?')) router.delete('/inventories/' + i.id, { preserveScroll: true });
};
</script>

<template>
    <Layout title="Pembukuan">
        <!-- Header gradient brand -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">PEMBUKUAN</h1>
                    <p class="text-brand-100 text-sm">Pemasukan, pengeluaran &amp; inventaris</p>
                </div>
                <a
                    v-if="payload.reportUrl"
                    :href="payload.reportUrl"
                    target="_blank"
                    rel="noreferrer"
                    class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V18a2 2 0 01-2 2z"
                        />
                    </svg>
                    Export PDF
                </a>
            </div>
        </header>

        <div class="px-6 py-6 space-y-6">
            <!-- Chart & rekap (read-only) -->
            <Pembukuan :data="payload" />

            <!-- ===== CRUD Transaksi ===== -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-700">Transaksi</h2>
                    <button
                        class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition"
                        @click="openTxCreate"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Transaksi
                    </button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-brand-100">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                                <th class="px-3 py-2.5 text-left">Tanggal</th>
                                <th class="px-3 py-2.5 text-left">Tipe</th>
                                <th class="px-3 py-2.5 text-left">Kategori</th>
                                <th class="px-3 py-2.5 text-left">Keterangan</th>
                                <th class="px-3 py-2.5 text-right">Jumlah</th>
                                <th class="px-3 py-2.5 text-center">Bukti</th>
                                <th class="px-3 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-50">
                            <tr v-for="t in transactions" :key="t.id" class="hover:bg-brand-50/60">
                                <td class="px-3 py-2.5 text-slate-600">{{ t.date }}</td>
                                <td class="px-3 py-2.5">
                                    <span
                                        :class="[
                                            'text-xs font-semibold px-2 py-0.5 rounded-full',
                                            t.type === 'pemasukan' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700',
                                        ]"
                                        >{{ types[t.type] || t.type }}</span
                                    >
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ t.category }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ t.description || '—' }}</td>
                                <td
                                    class="px-3 py-2.5 text-right font-medium"
                                    :class="t.type === 'pemasukan' ? 'text-emerald-600' : 'text-red-600'"
                                >
                                    {{ rp(t.amount_idr) }}
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <a
                                        v-if="t.bukti_path"
                                        :href="asset(t.bukti_path)"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="inline-block w-10 h-10 rounded-lg overflow-hidden border border-slate-200 hover:border-brand-400 transition"
                                    >
                                        <img :src="asset(t.bukti_path)" class="w-full h-full object-cover" alt="Bukti" />
                                    </a>
                                    <span v-else class="text-xs text-slate-300">—</span>
                                </td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <button class="text-slate-400 hover:text-brand-600 mr-2" title="Edit" @click="openTxEdit(t)">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z"
                                            />
                                        </svg>
                                    </button>
                                    <button class="text-slate-400 hover:text-red-600" title="Hapus" @click="delTx(t)">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="transactions.length === 0">
                                <td colspan="7" class="px-3 py-6 text-center text-slate-400">Belum ada transaksi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===== CRUD Inventaris ===== -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-700">Inventaris</h2>
                    <button
                        class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition"
                        @click="openInvCreate"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Inventaris
                    </button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-brand-100">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                                <th class="px-3 py-2.5 text-left">Bulan</th>
                                <th class="px-3 py-2.5 text-left">Barang</th>
                                <th class="px-3 py-2.5 text-right">Qty</th>
                                <th class="px-3 py-2.5 text-right">Nilai/unit</th>
                                <th class="px-3 py-2.5 text-right">Total</th>
                                <th class="px-3 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-50">
                            <tr v-for="i in inventories" :key="i.id" class="hover:bg-brand-50/60">
                                <td class="px-3 py-2.5 text-slate-600">{{ i.month }}</td>
                                <td class="px-3 py-2.5 text-slate-600">{{ i.name }}</td>
                                <td class="px-3 py-2.5 text-right">{{ i.qty }}</td>
                                <td class="px-3 py-2.5 text-right">{{ rp(i.unit_value_idr) }}</td>
                                <td class="px-3 py-2.5 text-right font-medium">{{ rp(i.total_value) }}</td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <button class="text-slate-400 hover:text-brand-600 mr-2" title="Edit" @click="openInvEdit(i)">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z"
                                            />
                                        </svg>
                                    </button>
                                    <button class="text-slate-400 hover:text-red-600" title="Hapus" @click="delInv(i)">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"
                                            />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="inventories.length === 0">
                                <td colspan="6" class="px-3 py-6 text-center text-slate-400">Belum ada inventaris.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== Modal Transaksi ===== -->
        <ModalWrap v-if="txOpen" width="max-w-md" @close="txOpen = false">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-brand-800">{{ txEditId ? 'Edit' : 'Tambah' }} Transaksi</h2>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none" @click="txOpen = false">
                    &times;
                </button>
            </div>
            <form class="space-y-3 text-sm" @submit.prevent="submitTx">
                    <!-- Scan struk (AI/OCR): baca foto struk → isi field otomatis, user tinggal cek & simpan -->
                    <div class="rounded-xl border border-dashed border-brand-300 bg-brand-50/60 p-3">
                        <label class="flex flex-wrap items-center gap-2 cursor-pointer">
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700"
                                :class="ocrLoading ? 'opacity-70 cursor-wait' : ''"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <circle cx="12" cy="13" r="3" stroke-width="2" />
                                </svg>
                                <span v-if="!ocrLoading">Scan struk (AI)</span>
                                <span v-else>Membaca struk…</span>
                            </span>
                            <input type="file" accept="image/*" class="hidden" :disabled="ocrLoading" @change="scanStruk" />
                            <span class="text-[11px] text-slate-500">Foto struk → field terisi otomatis, tinggal dicek.</span>
                        </label>
                        <p v-if="ocrError" class="text-xs text-red-600 mt-1.5">{{ ocrError }}</p>
                    </div>
                <label class="block font-medium text-slate-600"
                    >Tipe
                    <select
                        v-model="txForm.type"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    >
                        <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600"
                    >Kategori
                    <select
                        v-model="txForm.category"
                        @change="setTxCategory($event.target.value)"
                        required
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    >
                        <option value="" disabled>Pilih kategori…</option>
                        <option v-for="cat in availableCategories" :key="cat" :value="cat">{{ cat }}</option>
                        <option :value="OTHER_CATEGORY_VALUE">✏️ Lainnya (tulis manual)…</option>
                    </select>
                    <input
                        v-if="txForm.category === OTHER_CATEGORY_VALUE"
                        v-model="txForm.category_other"
                        required
                        placeholder="Tulis kategori…"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <span v-if="txForm.errors.category" class="text-xs text-red-600">{{ txForm.errors.category }}</span>
                </label>
                <label class="block font-medium text-slate-600"
                    >Keterangan (opsional)
                    <input
                        v-model="txForm.description"
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                </label>
                <label class="block font-medium text-slate-600"
                    >Jumlah (IDR)
                    <input
                        v-model="txForm.amount_idr"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <span v-if="txForm.errors.amount_idr" class="text-xs text-red-600">{{ txForm.errors.amount_idr }}</span>
                </label>
                <label class="block font-medium text-slate-600"
                    >Tanggal
                    <input
                        v-model="txForm.date"
                        type="date"
                        required
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <span v-if="txForm.errors.date" class="text-xs text-red-600">{{ txForm.errors.date }}</span>
                </label>
                <!-- Upload bukti pembayaran/nota -->
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Bukti (foto nota / screenshot)</label>
                    <div v-if="txEditId && transactions.find((t) => t.id === txEditId)?.bukti_path && !txForm.bukti" class="mb-2">
                        <img
                            :src="asset(transactions.find((t) => t.id === txEditId).bukti_path)"
                            class="w-24 h-24 object-cover rounded-lg border border-slate-200"
                            alt="Bukti lama"
                        />
                        <p class="text-[10px] text-slate-400 mt-0.5">Upload baru untuk mengganti</p>
                    </div>
                    <input
                        ref="txBuktiInput"
                        type="file"
                        accept="image/*"
                        class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold hover:file:bg-brand-100"
                        @change="txForm.bukti = $event.target.files[0]"
                    />
                    <p class="text-[10px] text-slate-400 mt-0.5">JPG/PNG/WebP, maks 5MB. Opsional.</p>
                    <span v-if="txForm.errors.bukti" class="text-xs text-red-600">{{ txForm.errors.bukti }}</span>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50"
                        @click="txOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="txForm.processing"
                        class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal Inventaris ===== -->
        <ModalWrap v-if="invOpen" width="max-w-md" @close="invOpen = false">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-brand-800">{{ invEditId ? 'Edit' : 'Tambah' }} Inventaris</h2>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none" @click="invOpen = false">
                    &times;
                </button>
            </div>
            <form class="space-y-3 text-sm" @submit.prevent="submitInv">
                <!-- OCR: foto barang/nota → AI isi field otomatis -->
                <div class="p-3 rounded-xl bg-brand-50/60 border border-brand-100">
                    <label class="flex flex-wrap items-center gap-2 cursor-pointer">
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700"
                            :class="invOcrLoading ? 'opacity-70 cursor-wait' : ''"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <circle cx="12" cy="13" r="3" stroke-width="2" />
                            </svg>
                            <span v-if="!invOcrLoading">Scan barang (AI)</span>
                            <span v-else>Membaca gambar…</span>
                        </span>
                        <input type="file" accept="image/*" class="hidden" :disabled="invOcrLoading" @change="scanInventaris" />
                        <span class="text-[11px] text-slate-500">Foto barang/nota → field terisi otomatis, tinggal dicek.</span>
                    </label>
                    <p v-if="invOcrError" class="text-xs text-red-600 mt-1.5">{{ invOcrError }}</p>
                </div>
                <label class="block font-medium text-slate-600"
                    >Nama Barang
                    <input
                        v-model="invForm.name"
                        required
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <span v-if="invForm.errors.name" class="text-xs text-red-600">{{ invForm.errors.name }}</span>
                </label>
                <label class="block font-medium text-slate-600"
                    >Jumlah (Qty)
                    <input
                        v-model="invForm.qty"
                        type="number"
                        min="0"
                        required
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <span v-if="invForm.errors.qty" class="text-xs text-red-600">{{ invForm.errors.qty }}</span>
                </label>
                <label class="block font-medium text-slate-600"
                    >Nilai per Unit (IDR)
                    <input
                        v-model="invForm.unit_value_idr"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <span v-if="invForm.errors.unit_value_idr" class="text-xs text-red-600">{{ invForm.errors.unit_value_idr }}</span>
                </label>
                <label class="block font-medium text-slate-600"
                    >Bulan
                    <input
                        v-model="invForm.month"
                        type="month"
                        required
                        class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"
                    />
                    <span v-if="invForm.errors.month" class="text-xs text-red-600">{{ invForm.errors.month }}</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50"
                        @click="invOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="invForm.processing"
                        class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

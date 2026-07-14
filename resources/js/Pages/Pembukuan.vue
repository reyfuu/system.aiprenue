<script setup>
// Halaman Pembukuan: chart (read-only) + CRUD transaksi & inventaris (super_admin/it).
import { ref } from 'vue';                          // state modal/edit
import { useForm, router } from '@inertiajs/vue3';   // form Inertia + aksi hapus
import Layout from '../Layout.vue';                  // kerangka + sidebar + toast
import Pembukuan from '../scripts/components/Pembukuan.vue'; // komponen chart (prop `data`)
import ModalWrap from '../ModalWrap.vue';            // pembungkus modal
import { rp } from '../scripts/lib/format';          // format Rupiah

// Props dari controller
const props = defineProps({
    payload: Object,        // data chart/rekap
    transactions: Array,    // daftar transaksi mentah
    inventories: Array,     // daftar inventaris mentah
    types: Object,          // peta pemasukan/pengeluaran
});

// ---- Modal Transaksi ----
const txOpen = ref(false);                           // buka/tutup modal transaksi
const txEditId = ref(null);                          // id transaksi yg diedit (null = create)
const txForm = useForm({ type: 'pemasukan', category: '', description: '', amount_idr: '', date: '' });
const openTxCreate = () => { txEditId.value = null; txForm.reset(); txForm.clearErrors(); txOpen.value = true; };
const openTxEdit = (t) => {                           // isi form dari baris
    txEditId.value = t.id;
    txForm.type = t.type; txForm.category = t.category; txForm.description = t.description ?? '';
    txForm.amount_idr = t.amount_idr; txForm.date = t.date;
    txForm.clearErrors(); txOpen.value = true;
};
const submitTx = () => {
    const done = { onSuccess: () => (txOpen.value = false), preserveScroll: true };
    if (txEditId.value) txForm.put('/transactions/' + txEditId.value, done); // update
    else txForm.post('/transactions', done);                                 // create
};
const delTx = (t) => { if (confirm('Hapus transaksi ini?')) router.delete('/transactions/' + t.id, { preserveScroll: true }); };

// ---- Modal Inventaris ----
const invOpen = ref(false);
const invEditId = ref(null);
const invForm = useForm({ name: '', qty: '', unit_value_idr: '', month: '' });
const openInvCreate = () => { invEditId.value = null; invForm.reset(); invForm.clearErrors(); invOpen.value = true; };
const openInvEdit = (i) => {
    invEditId.value = i.id;
    invForm.name = i.name; invForm.qty = i.qty; invForm.unit_value_idr = i.unit_value_idr; invForm.month = i.month;
    invForm.clearErrors(); invOpen.value = true;
};
const submitInv = () => {
    const done = { onSuccess: () => (invOpen.value = false), preserveScroll: true };
    // input type=month → 'YYYY-MM'; server minta date → tambahkan '-01'
    invForm.transform((d) => ({ ...d, month: d.month && d.month.length === 7 ? d.month + '-01' : d.month }));
    if (invEditId.value) invForm.put('/inventories/' + invEditId.value, done); // update
    else invForm.post('/inventories', done);                                   // create
};
const delInv = (i) => { if (confirm('Hapus inventaris ini?')) router.delete('/inventories/' + i.id, { preserveScroll: true }); };
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
                <a v-if="payload.reportUrl" :href="payload.reportUrl" target="_blank" rel="noreferrer"
                   class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V18a2 2 0 01-2 2z" /></svg>
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
                    <button @click="openTxCreate" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
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
                                <th class="px-3 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-50">
                            <tr v-for="t in transactions" :key="t.id" class="hover:bg-brand-50/60">
                                <td class="px-3 py-2.5 text-slate-600">{{ t.date }}</td>
                                <td class="px-3 py-2.5">
                                    <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', t.type === 'pemasukan' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700']">{{ types[t.type] || t.type }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-slate-600">{{ t.category }}</td>
                                <td class="px-3 py-2.5 text-slate-500">{{ t.description || '—' }}</td>
                                <td class="px-3 py-2.5 text-right font-medium" :class="t.type === 'pemasukan' ? 'text-emerald-600' : 'text-red-600'">{{ rp(t.amount_idr) }}</td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <button @click="openTxEdit(t)" class="text-slate-400 hover:text-brand-600 mr-2" title="Edit">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z" /></svg>
                                    </button>
                                    <button @click="delTx(t)" class="text-slate-400 hover:text-red-600" title="Hapus">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="transactions.length === 0"><td colspan="6" class="px-3 py-6 text-center text-slate-400">Belum ada transaksi.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===== CRUD Inventaris ===== -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-700">Inventaris</h2>
                    <button @click="openInvCreate" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
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
                                    <button @click="openInvEdit(i)" class="text-slate-400 hover:text-brand-600 mr-2" title="Edit">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z" /></svg>
                                    </button>
                                    <button @click="delInv(i)" class="text-slate-400 hover:text-red-600" title="Hapus">
                                        <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="inventories.length === 0"><td colspan="6" class="px-3 py-6 text-center text-slate-400">Belum ada inventaris.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== Modal Transaksi ===== -->
        <ModalWrap v-if="txOpen" width="max-w-md" @close="txOpen = false">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-brand-800">{{ txEditId ? 'Edit' : 'Tambah' }} Transaksi</h2>
                <button type="button" @click="txOpen = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>
            <form @submit.prevent="submitTx" class="space-y-3 text-sm">
                <label class="block font-medium text-slate-600">Tipe
                    <select v-model="txForm.type" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>
                <label class="block font-medium text-slate-600">Kategori
                    <input v-model="txForm.category" required placeholder="mis. Endorse, Gaji, Iklan…" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="txForm.errors.category" class="text-xs text-red-600">{{ txForm.errors.category }}</span>
                </label>
                <label class="block font-medium text-slate-600">Keterangan (opsional)
                    <input v-model="txForm.description" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="block font-medium text-slate-600">Jumlah (IDR)
                    <input type="number" step="0.01" min="0" v-model="txForm.amount_idr" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="txForm.errors.amount_idr" class="text-xs text-red-600">{{ txForm.errors.amount_idr }}</span>
                </label>
                <label class="block font-medium text-slate-600">Tanggal
                    <input type="date" v-model="txForm.date" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="txForm.errors.date" class="text-xs text-red-600">{{ txForm.errors.date }}</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="txOpen = false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="txForm.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal Inventaris ===== -->
        <ModalWrap v-if="invOpen" width="max-w-md" @close="invOpen = false">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-brand-800">{{ invEditId ? 'Edit' : 'Tambah' }} Inventaris</h2>
                <button type="button" @click="invOpen = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>
            <form @submit.prevent="submitInv" class="space-y-3 text-sm">
                <label class="block font-medium text-slate-600">Nama Barang
                    <input v-model="invForm.name" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="invForm.errors.name" class="text-xs text-red-600">{{ invForm.errors.name }}</span>
                </label>
                <label class="block font-medium text-slate-600">Jumlah (Qty)
                    <input type="number" min="0" v-model="invForm.qty" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="invForm.errors.qty" class="text-xs text-red-600">{{ invForm.errors.qty }}</span>
                </label>
                <label class="block font-medium text-slate-600">Nilai per Unit (IDR)
                    <input type="number" step="0.01" min="0" v-model="invForm.unit_value_idr" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="invForm.errors.unit_value_idr" class="text-xs text-red-600">{{ invForm.errors.unit_value_idr }}</span>
                </label>
                <label class="block font-medium text-slate-600">Bulan
                    <input type="month" v-model="invForm.month" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="invForm.errors.month" class="text-xs text-red-600">{{ invForm.errors.month }}</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="invOpen = false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="invForm.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">Simpan</button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

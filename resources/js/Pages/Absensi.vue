<script setup>
// Absensi: semua peran bisa mengajukan cuti/sakit/izin + lihat riwayat.
// Tim manajemen (canManage) lihat semua pengajuan & setujui/tolak.
import { ref, computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import ModalWrap from '../ModalWrap.vue';

const props = defineProps({
    absences:  Array,   // daftar pengajuan
    types:     Object,  // key jenis -> label (cuti/sakit/izin)
    statuses:  Object,  // key status -> label
    canManage: Boolean, // boleh lihat semua + approve
});

const meId = usePage().props.auth?.user?.id;   // untuk tombol hapus milik sendiri

const open = ref(false);                        // modal pengajuan
const fileName = ref('');                        // nama file terpilih (tampilan)

// Field TOP-LEVEL sesuai konvensi useForm repo ini.
const form = useForm({
    type: 'cuti',
    start_date: '',
    end_date: '',
    reason: '',
    attachment: null,
});

const openForm = () => {
    form.reset();
    form.clearErrors();
    fileName.value = '';
    open.value = true;
};

const pickFile = (e) => {
    form.attachment = e.target.files[0] || null;
    fileName.value = form.attachment ? form.attachment.name : '';
};

const submit = () => {
    // forceFormData: wajib supaya file ikut terkirim (multipart).
    form.post('/absensi', {
        forceFormData: true,
        onSuccess: () => { open.value = false; form.reset(); fileName.value = ''; },
    });
};

const setStatus = (a, status) => router.patch(`/absensi/${a.id}/status`, { status }, { preserveScroll: true });
const hapus = (a) => { if (confirm('Hapus pengajuan ini?')) router.delete(`/absensi/${a.id}`, { preserveScroll: true }); };

// Rentang tanggal terbaca: satu hari atau "awal – akhir".
const tanggal = (a) => a.end_date && a.end_date !== a.start_date ? `${a.start_date} – ${a.end_date}` : a.start_date;

const typeClass = (t) => ({
    cuti:  'bg-blue-50 text-blue-700',
    sakit: 'bg-red-50 text-red-700',
    izin:  'bg-amber-50 text-amber-700',
}[t] || 'bg-slate-100 text-slate-600');

const statusClass = (s) => ({
    menunggu:  'bg-amber-50 text-amber-700',
    disetujui: 'bg-emerald-50 text-emerald-700',
    ditolak:   'bg-red-50 text-red-700',
}[s] || 'bg-slate-100 text-slate-600');

const empty = computed(() => props.absences.length === 0);
</script>

<template>
    <Layout title="Absensi">
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">ABSENSI</h1>
                    <p class="text-brand-100 text-sm">Pengajuan cuti, sakit, dan izin</p>
                </div>
                <button @click="openForm"
                        class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow transition">
                    + Ajukan
                </button>
            </div>
        </header>

        <div class="p-6">
            <div class="bg-white border border-brand-100 rounded-2xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-brand-100 bg-brand-50/60 text-left text-slate-600">
                            <th v-if="canManage" class="px-4 py-3 font-semibold">Nama</th>
                            <th class="px-4 py-3 font-semibold">Jenis</th>
                            <th class="px-4 py-3 font-semibold whitespace-nowrap">Tanggal</th>
                            <th class="px-4 py-3 font-semibold">Keterangan</th>
                            <th class="px-4 py-3 font-semibold">Lampiran</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="empty">
                            <td :colspan="canManage ? 7 : 6" class="px-4 py-10 text-center text-slate-400">
                                Belum ada pengajuan. Klik “Ajukan” untuk membuat.
                            </td>
                        </tr>
                        <tr v-for="a in absences" :key="a.id" class="border-b border-brand-50 last:border-0 align-top">
                            <td v-if="canManage" class="px-4 py-3 font-medium text-slate-700 whitespace-nowrap">{{ a.user }}</td>
                            <td class="px-4 py-3">
                                <span :class="['text-xs font-semibold px-2 py-0.5 rounded', typeClass(a.type)]">{{ types[a.type] || a.type }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ tanggal(a) }}</td>
                            <td class="px-4 py-3 text-slate-600 max-w-xs">{{ a.reason || '—' }}</td>
                            <td class="px-4 py-3">
                                <a v-if="a.attachment_url" :href="a.attachment_url" target="_blank"
                                   class="text-brand-600 hover:text-brand-800 text-xs font-medium underline">Lihat</a>
                                <span v-else class="text-slate-300 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['text-xs font-semibold px-2 py-0.5 rounded', statusClass(a.status)]">{{ statuses[a.status] || a.status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Manajemen: setujui/tolak selama masih menunggu -->
                                    <template v-if="canManage && a.status === 'menunggu'">
                                        <button @click="setStatus(a, 'disetujui')" class="text-xs font-semibold text-emerald-600 hover:text-emerald-800">Setujui</button>
                                        <button @click="setStatus(a, 'ditolak')" class="text-xs font-semibold text-red-600 hover:text-red-800">Tolak</button>
                                    </template>
                                    <!-- Hapus: pemilik atau manajemen -->
                                    <button v-if="a.user_id === meId || canManage" @click="hapus(a)"
                                            class="text-xs font-medium text-slate-400 hover:text-red-600">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal pengajuan -->
        <ModalWrap v-if="open" width="max-w-md" @close="open = false">
            <h2 class="text-lg font-bold text-brand-800 mb-5">Ajukan Absensi</h2>
            <form @submit.prevent="submit" class="space-y-4 text-sm">
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Jenis</label>
                    <select v-model="form.type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 bg-white focus:ring-2 focus:ring-brand-400 outline-none">
                        <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <span v-if="form.errors.type" class="text-xs text-red-600 mt-1 block">{{ form.errors.type }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Tanggal Mulai</label>
                        <input type="date" v-model="form.start_date" required
                               class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.errors.start_date" class="text-xs text-red-600 mt-1 block">{{ form.errors.start_date }}</span>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Sampai <span class="text-slate-400 font-normal">(opsional)</span></label>
                        <input type="date" v-model="form.end_date"
                               class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.errors.end_date" class="text-xs text-red-600 mt-1 block">{{ form.errors.end_date }}</span>
                    </div>
                </div>
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Keterangan</label>
                    <textarea v-model="form.reason" rows="3" placeholder="Alasan singkat…"
                              class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                    <span v-if="form.errors.reason" class="text-xs text-red-600 mt-1 block">{{ form.errors.reason }}</span>
                </div>
                <div>
                    <label class="block font-medium text-slate-600 mb-1">
                        Lampiran keterangan
                        <span class="text-slate-400 font-normal">(mis. surat dokter untuk sakit)</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <label for="absensi-file" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold">
                            Pilih file
                        </label>
                        <input id="absensi-file" type="file" accept=".jpg,.jpeg,.png,.pdf" @change="pickFile" class="hidden" />
                        <span class="flex-1 text-xs text-slate-500 truncate">{{ fileName || 'Belum ada file (opsional)' }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">JPG/PNG/PDF, maks 10 MB.</p>
                    <span v-if="form.errors.attachment" class="text-xs text-red-600 mt-1 block">{{ form.errors.attachment }}</span>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="open = false" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition">Batal</button>
                    <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">
                        {{ form.processing ? 'Mengirim…' : 'Kirim' }}
                    </button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

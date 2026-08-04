<script setup>
import { computed, ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import ModalWrap from '../ModalWrap.vue';

const props = defineProps({
    absences: Array,
    types: Object,
    statuses: Object,
    canManage: Boolean,
    attendanceCanManage: Boolean,
    attendances: { type: Array, default: () => [] },
    attendanceUsers: { type: Array, default: () => [] },
    attendanceMonth: { type: String, default: '' },
    attendanceMonthOptions: { type: Array, default: () => [] },
    attendanceSummary: { type: Object, default: () => ({ totalRecords: 0, todayHasRecord: false }) },
});

const meId = usePage().props.auth?.user?.id;
const today = new Date().toISOString().slice(0, 10);
const isMonthActive = (value) => value === (props.attendanceMonth || '');

const presenceForm = useForm({
    user_id: '',
    work_date: today,
    check_in_time: '',
    check_out_time: '',
    note: '',
});

const absenceForm = useForm({
    type: 'cuti',
    start_date: '',
    end_date: '',
    reason: '',
    attachment: null,
});

const openPresence = ref(false);
const editPresenceId = ref(null);

const isAbsenceOpen = ref(false);

const fileName = ref('');
const presenceFileName = ref('');
const activePresenceEdit = ref(null);

const hadir = () => {
    absenceForm.reset();
    absenceForm.start_date = today;
    isAbsenceOpen.value = true;
};

const hadirSimpan = () => {
    absenceForm.post('/absensi', {
        forceFormData: true,
        onSuccess: () => {
            isAbsenceOpen.value = false;
            absenceForm.reset();
        },
    });
};

const bukaManual = () => {
    editPresenceId.value = null;
    activePresenceEdit.value = null;
    presenceForm.reset();
    presenceForm.user_id = String(props.attendanceUsers[0]?.id ?? '');
    presenceForm.work_date = today;
    presenceFileName.value = '';
    openPresence.value = true;
};

const editPresence = (item) => {
    editPresenceId.value = item.id;
    activePresenceEdit.value = item;
    presenceForm.user_id = String(item.user_id ?? '');
    presenceForm.work_date = item.date;
    presenceForm.check_in_time = item.check_in || '';
    presenceForm.check_out_time = item.check_out || '';
    presenceForm.note = item.note || '';
    openPresence.value = true;
};

const submitPresence = () => {
    const url = editPresenceId.value ? `/absensi/presensi/${editPresenceId.value}` : '/absensi/presensi';
    const method = editPresenceId.value ? presenceForm.patch : presenceForm.post;

    method.call(presenceForm, url, {
        onSuccess: () => {
            openPresence.value = false;
            presenceForm.reset();
            editPresenceId.value = null;
            activePresenceEdit.value = null;
        },
    });
};

const removePresence = (item) => {
    if (!confirm('Hapus data presensi ini?')) return;
    router.delete(`/absensi/presensi/${item.id}`, { preserveScroll: true });
};

const clockIn = () => router.post('/absensi/check-in', {}, { preserveScroll: true });
const clockOut = () => router.post('/absensi/check-out', {}, { preserveScroll: true });

const setPresenceMonth = (value) => router.get('/absensi', { month: value }, { preserveState: false });

const tanggal = (a) => a.date;
const formatMinute = (m) => `${Math.floor((m ?? 0) / 60)}j ${Math.max(0, (m ?? 0) % 60)}m`;

const typeClass = (t) =>
    ({
        cuti: 'bg-blue-50 text-blue-700',
        sakit: 'bg-red-50 text-red-700',
        izin: 'bg-amber-50 text-amber-700',
    })[t] || 'bg-slate-100 text-slate-600';

const statusClass = (s) =>
    ({
        menunggu: 'bg-amber-50 text-amber-700',
        disetujui: 'bg-emerald-50 text-emerald-700',
        ditolak: 'bg-red-50 text-red-700',
    })[s] || 'bg-slate-100 text-slate-600';

const statusPresence = (item) => {
    if (item.is_weekend) return 'Libur';
    if (!item.check_in && !item.check_out) return 'Tidak hadir';
    if (item.check_in && item.check_out) return 'Lengkap';
    if (item.check_in && !item.check_out) return 'Masih aktif';
    return 'Data belum lengkap';
};

const presenceStatusClass = (item) => ({
    Lengkap: 'bg-emerald-50 text-emerald-700',
    'Masih aktif': 'bg-blue-50 text-blue-700',
    'Data belum lengkap': 'bg-amber-50 text-amber-700',
    'Tidak hadir': 'bg-slate-50 text-slate-600',
    Libur: 'bg-purple-50 text-purple-700',
})[statusPresence(item)] || 'bg-slate-50 text-slate-700';
</script>

<template>
    <Layout title="Absensi">
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex flex-wrap gap-3 items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">ABSENSI</h1>
                    <p class="text-brand-100 text-sm">Absensi harian + pengajuan cuti, sakit, dan izin</p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-4 py-2 rounded-xl shadow" @click="hadir">
                        + Ajukan
                    </button>
                    <button v-if="attendanceCanManage" class="bg-brand-100 text-brand-800 hover:bg-brand-50 text-sm font-semibold px-4 py-2 rounded-xl shadow" @click="bukaManual">
                        Manual Presensi
                    </button>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <section class="bg-white border border-brand-100 rounded-2xl shadow-sm p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h2 class="font-semibold text-brand-800">Presensi Harian</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg" @click="clockIn">Check In</button>
                        <button class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg" @click="clockOut">Check Out</button>
                        <select
                            :value="attendanceMonth"
                            class="border border-brand-100 rounded-lg px-3 py-2 text-sm"
                            @change="setPresenceMonth($event.target.value)"
                        >
                            <option v-for="b in attendanceMonthOptions" :key="b.value" :value="b.value">
                                {{ b.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-brand-100 bg-brand-50/60 text-left text-slate-600">
                                <th class="px-4 py-2.5 font-semibold">Tanggal</th>
                                <th class="px-4 py-2.5 font-semibold">Nama</th>
                                <th class="px-4 py-2.5 font-semibold">Check In</th>
                                <th class="px-4 py-2.5 font-semibold">Check Out</th>
                                <th class="px-4 py-2.5 font-semibold">Ringkas</th>
                                <th class="px-4 py-2.5 font-semibold">Keterlambatan</th>
                                <th class="px-4 py-2.5 font-semibold">Lembur</th>
                                <th class="px-4 py-2.5 font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="attendances.length === 0">
                                <td :colspan="8" class="px-4 py-8 text-center text-slate-400">
                                    Belum ada data presensi pada periode ini.
                                </td>
                            </tr>
                            <tr v-for="a in attendances" :key="a.id" class="border-b border-brand-50 last:border-0">
                                <td class="px-4 py-2.5 text-slate-600">{{ tanggal(a) }}</td>
                                <td class="px-4 py-2.5 font-medium text-slate-700">{{ a.user || '-' }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ a.check_in || '-' }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ a.check_out || '-' }}</td>
                                <td class="px-4 py-2.5">
                                    <span :class="['text-xs font-semibold px-2 py-0.5 rounded', presenceStatusClass(a)]">
                                        {{ statusPresence(a) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-600">{{ formatMinute(a.late_minutes) }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ formatMinute(a.overtime_minutes) }}</td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <button
                                        v-if="attendanceCanManage"
                                        class="text-xs text-brand-700 hover:text-brand-900"
                                        @click="editPresence(a)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="attendanceCanManage"
                                        class="text-xs text-red-600 hover:text-red-800 ml-2"
                                        @click="removePresence(a)"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white border border-brand-100 rounded-2xl shadow-sm p-4 sm:p-6">
                <h2 class="font-semibold text-brand-800 mb-4">Pengajuan Cuti / Sakit / Izin</h2>
                <div class="overflow-x-auto">
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
                            <tr v-if="absences.length === 0">
                                <td :colspan="canManage ? 7 : 6" class="px-4 py-10 text-center text-slate-400">
                                    Belum ada pengajuan. Klik “+ Ajukan”.
                                </td>
                            </tr>
                            <tr v-for="a in absences" :key="a.id" class="border-b border-brand-50 last:border-0 align-top">
                                <td v-if="canManage" class="px-4 py-3 font-medium text-slate-700 whitespace-nowrap">{{ a.user }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['text-xs font-semibold px-2 py-0.5 rounded', typeClass(a.type)]">{{ types[a.type] || a.type }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                    {{ a.end_date && a.end_date !== a.start_date ? `${a.start_date} – ${a.end_date}` : a.start_date }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 max-w-xs">{{ a.reason || '—' }}</td>
                                <td class="px-4 py-3">
                                    <a v-if="a.attachment_url" :href="a.attachment_url" target="_blank" class="text-brand-600 hover:text-brand-800 text-xs font-medium underline">Lihat</a>
                                    <span v-else class="text-slate-300 text-xs">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['text-xs font-semibold px-2 py-0.5 rounded', statusClass(a.status)]">{{ statuses[a.status] || a.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <template v-if="canManage && a.status === 'menunggu'">
                                            <button class="text-xs font-semibold text-emerald-600 hover:text-emerald-800" @click="$inertia.patch(`/absensi/${a.id}/status`, { status: 'disetujui' })">
                                                Setujui
                                            </button>
                                            <button class="text-xs font-semibold text-red-600 hover:text-red-800" @click="$inertia.patch(`/absensi/${a.id}/status`, { status: 'ditolak' })">
                                                Tolak
                                            </button>
                                        </template>
                                        <button
                                            v-if="a.user_id === meId || canManage"
                                            class="text-xs font-medium text-slate-400 hover:text-red-600"
                                            @click="$inertia.delete(`/absensi/${a.id}`)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <ModalWrap v-if="isAbsenceOpen" width="max-w-md" @close="isAbsenceOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-5">Ajukan Absensi</h2>
            <form class="space-y-4 text-sm" @submit.prevent="hadirSimpan">
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Jenis</label>
                    <select v-model="absenceForm.type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 bg-white focus:ring-2 focus:ring-brand-400 outline-none">
                        <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <span v-if="absenceForm.errors.type" class="text-xs text-red-600 mt-1 block">{{ absenceForm.errors.type }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Tanggal Mulai</label>
                        <input v-model="absenceForm.start_date" type="date" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="absenceForm.errors.start_date" class="text-xs text-red-600 mt-1 block">{{ absenceForm.errors.start_date }}</span>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Sampai <span class="text-slate-400 font-normal">(opsional)</span></label>
                        <input v-model="absenceForm.end_date" type="date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="absenceForm.errors.end_date" class="text-xs text-red-600 mt-1 block">{{ absenceForm.errors.end_date }}</span>
                    </div>
                </div>
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Keterangan</label>
                    <textarea v-model="absenceForm.reason" rows="3" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                    <span v-if="absenceForm.errors.reason" class="text-xs text-red-600 mt-1 block">{{ absenceForm.errors.reason }}</span>
                </div>
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Lampiran</label>
                    <div class="flex items-center gap-2">
                        <label for="absensi-file" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold">
                            Pilih file
                        </label>
                        <input id="absensi-file" type="file" accept=".jpg,.jpeg,.png,.pdf" class="hidden" @change="(e) => {
                            absenceForm.attachment = e.target.files[0] || null;
                            fileName.value = absenceForm.attachment ? absenceForm.attachment.name : '';
                        }" />
                        <span class="flex-1 text-xs text-slate-500 truncate">{{ fileName || 'Belum ada file (opsional)' }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">JPG/PNG/PDF, maks 10 MB.</p>
                    <span v-if="absenceForm.errors.attachment" class="text-xs text-red-600 mt-1 block">{{ absenceForm.errors.attachment }}</span>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition" @click="isAbsenceOpen = false">Batal</button>
                    <button type="submit" :disabled="absenceForm.processing" class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">
                        {{ absenceForm.processing ? 'Mengirim…' : 'Kirim' }}
                    </button>
                </div>
            </form>
        </ModalWrap>

        <ModalWrap v-if="openPresence" width="max-w-md" @close="openPresence = false">
            <h2 class="text-lg font-bold text-brand-800 mb-5">Presensi Manual</h2>
            <form class="space-y-3 text-sm" @submit.prevent="submitPresence">
                <label class="block font-medium text-slate-600">
                    Karyawan
                    <select v-model="presenceForm.user_id" class="w-full border border-slate-200 rounded-xl px-3 py-2 mt-1">
                        <option value="">Pilih</option>
                        <option v-for="u in attendanceUsers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                    </select>
                    <span v-if="presenceForm.errors.user_id" class="text-xs text-red-600">{{ presenceForm.errors.user_id }}</span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block font-medium text-slate-600">
                        Tanggal
                        <input v-model="presenceForm.work_date" type="date" required class="w-full border border-slate-200 rounded-xl px-3 py-2 mt-1" />
                        <span v-if="presenceForm.errors.work_date" class="text-xs text-red-600">{{ presenceForm.errors.work_date }}</span>
                    </label>
                    <div />
                </div>
                <label class="block font-medium text-slate-600">
                    Jam Masuk (HH:MM)
                    <input v-model="presenceForm.check_in_time" type="time" class="w-full border border-slate-200 rounded-xl px-3 py-2 mt-1" />
                    <span v-if="presenceForm.errors.check_in_time" class="text-xs text-red-600">{{ presenceForm.errors.check_in_time }}</span>
                </label>
                <label class="block font-medium text-slate-600">
                    Jam Pulang (HH:MM)
                    <input v-model="presenceForm.check_out_time" type="time" class="w-full border border-slate-200 rounded-xl px-3 py-2 mt-1" />
                    <span v-if="presenceForm.errors.check_out_time" class="text-xs text-red-600">{{ presenceForm.errors.check_out_time }}</span>
                </label>
                <label class="block font-medium text-slate-600">
                    Catatan
                    <textarea v-model="presenceForm.note" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2 mt-1"></textarea>
                    <span v-if="presenceForm.errors.note" class="text-xs text-red-600">{{ presenceForm.errors.note }}</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50" @click="openPresence = false">Batal</button>
                    <button type="submit" :disabled="presenceForm.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white">
                        Simpan
                    </button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

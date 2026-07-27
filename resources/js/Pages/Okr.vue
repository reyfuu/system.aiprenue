<script setup>
// Halaman OKR: ringkasan "helicopter view" + daftar Objective (sasaran) &
// Key Result (ukuran keberhasilan). Key result dilacak sederhana: selesai /
// belum (radio). Progress objective = % KR yang selesai. CRUD lewat modal;
// tombol tambah/edit/hapus hanya untuk tim pengelola (auth.user.canManage).
// Layout dibuat mobile-first: menumpuk di layar sempit, melebar di layar besar.
import { ref, computed } from 'vue';                 // state modal & turunan reaktif
import { Link, useForm, router, usePage } from '@inertiajs/vue3'; // form Inertia, navigasi, aksi hapus, props
import Layout from '../Layout.vue';                  // kerangka (sidebar + toast)
import ModalWrap from '../ModalWrap.vue';            // pembungkus modal seragam

// Props dari OkrController@index
const props = defineProps({
    objectives: { type: Array, default: () => [] },  // objective + relasi key_results
});

const page = usePage();
const auth = computed(() => page.props.auth);        // untuk cek canManage

// ── Hitung progress ────────────────────────────────────────────────
// Progress objective = jumlah KR selesai / total KR (0% bila belum ada KR).
const objPct = (obj) => {
    const krs = obj.key_results || [];
    if (!krs.length) return 0;
    return Math.round((krs.filter((k) => k.completed).length / krs.length) * 100);
};
// Warna bar mengikuti capaian: merah < 34, kuning < 67, hijau sisanya.
// Kelas ditulis literal supaya terbaca scanner Tailwind (lihat CLAUDE.md).
const barColor = (pct) => (pct < 34 ? 'bg-red-500' : pct < 67 ? 'bg-amber-500' : 'bg-emerald-500');

// ── Filter periode (mis. "Q3 2026") ─────────────────────────────────
// Klien saja: semua objective sudah dimuat, jadi cukup saring di sini.
// Pilihan diambil dari period yang benar-benar ada, urut terbaru dulu.
const periodeAktif = ref('');
const daftarPeriode = computed(() => [...new Set(props.objectives.map((o) => o.period).filter(Boolean))].sort().reverse());
const objectivesTampil = computed(() => (periodeAktif.value ? props.objectives.filter((o) => o.period === periodeAktif.value) : props.objectives));

// ── Helicopter view: ringkasan lintas objective (ikut filter periode) ──
const ringkas = computed(() => {
    const list = objectivesTampil.value;
    const n = list.length;
    const avg = n ? Math.round(list.reduce((s, o) => s + objPct(o), 0) / n) : 0;
    const selesai = list.filter((o) => (o.key_results || []).length && objPct(o) === 100).length;
    const lewat = list.filter((o) => isOverdue(o)).length; // deadline terlewat & belum tuntas
    return { total: n, avg, selesai, lewat };
});

// ── Deadline ────────────────────────────────────────────────────────
const fmtTanggal = (iso) => new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
// Terlewat = ada deadline, sudah lewat hari ini, dan objective belum 100%.
const isOverdue = (obj) => {
    if (!obj.deadline || objPct(obj) === 100) return false;
    const d = new Date(obj.deadline); d.setHours(0, 0, 0, 0);
    const kini = new Date(); kini.setHours(0, 0, 0, 0);
    return d < kini;
};

// ── Form CRUD ──────────────────────────────────────────────────────
const modalOpen = ref(false);       // tampil/sembunyi modal
const editId = ref(null);           // null = tambah, angka = edit objective itu

// Field top-level (konvensi useForm Inertia). key_results = array baris KR.
const form = useForm({
    title: '',
    period: '',
    owner: '',
    deadline: '',
    description: '',
    key_results: [],
});

// Satu baris KR kosong (dipakai saat tambah objective / tambah baris).
const barisKrKosong = () => ({ title: '', completed: false });

// Buka modal TAMBAH: reset form ke satu KR kosong.
const openCreate = () => {
    editId.value = null;
    form.reset();
    form.clearErrors();
    form.key_results = [barisKrKosong()];
    modalOpen.value = true;
};

// Buka modal EDIT: isi form dari objective terpilih (KR ikut disalin).
const openEdit = (obj) => {
    editId.value = obj.id;
    form.clearErrors();
    form.title = obj.title;
    form.period = obj.period ?? '';
    form.owner = obj.owner ?? '';
    form.deadline = obj.deadline ? String(obj.deadline).slice(0, 10) : ''; // input date butuh yyyy-mm-dd
    form.description = obj.description ?? '';
    form.key_results = (obj.key_results || []).map((kr) => ({ title: kr.title, completed: !!kr.completed }));
    modalOpen.value = true;
};

const tambahBaris = () => form.key_results.push(barisKrKosong());     // tambah KR di modal
const hapusBaris = (i) => form.key_results.splice(i, 1);             // buang satu KR

// Submit: POST utk tambah, PUT utk edit. Sukses → tutup & kosongkan.
const submit = () => {
    const opsi = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; form.reset(); } };
    if (editId.value) {
        form.put(`/okr/${editId.value}`, opsi);
    } else {
        form.post('/okr', opsi);
    }
};

// Hapus objective (KR ikut terhapus di server via cascade). Konfirmasi dulu.
const hapus = (obj) => {
    if (!confirm(`Hapus objective "${obj.title}" beserta key result-nya?`)) return;
    router.delete(`/okr/${obj.id}`, { preserveScroll: true });
};
</script>

<template>
    <Layout title="OKR">
        <div class="max-w-5xl mx-auto px-4 sm:px-0">
            <!-- Header + tombol tambah (menumpuk di hp) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
                <div>
                    <h1 class="text-xl font-bold text-slate-800">OKR</h1>
                    <p class="text-sm text-slate-500">Objective &amp; Key Result — sasaran dan ukuran keberhasilannya.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                    <!-- Filter periode/kuartal — hanya muncul kalau ada periode terisi -->
                    <select v-if="daftarPeriode.length" v-model="periodeAktif"
                            class="w-full sm:w-auto border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">Semua periode</option>
                        <option v-for="p in daftarPeriode" :key="p" :value="p">{{ p }}</option>
                    </select>
                    <button v-if="auth?.user?.canManage" @click="openCreate"
                            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition">
                        + Objective
                    </button>
                </div>
            </div>

            <!-- Helicopter view: ringkasan lintas objective (ikut filter periode) -->
            <div v-if="objectivesTampil.length" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                <div class="bg-white rounded-2xl border border-brand-100 p-4">
                    <p class="text-xs text-slate-500">Objective</p>
                    <p class="text-2xl font-bold text-slate-800">{{ ringkas.total }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-brand-100 p-4">
                    <p class="text-xs text-slate-500">Rata-rata progress</p>
                    <p class="text-2xl font-bold text-brand-700">{{ ringkas.avg }}%</p>
                </div>
                <div class="bg-white rounded-2xl border border-brand-100 p-4">
                    <p class="text-xs text-slate-500">Tuntas</p>
                    <p class="text-2xl font-bold text-emerald-600">{{ ringkas.selesai }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-brand-100 p-4">
                    <p class="text-xs text-slate-500">Lewat deadline</p>
                    <p class="text-2xl font-bold" :class="ringkas.lewat ? 'text-red-600' : 'text-slate-800'">{{ ringkas.lewat }}</p>
                </div>
            </div>

            <!-- Kosong: belum ada data sama sekali -->
            <div v-if="!objectives.length" class="bg-white rounded-2xl border border-brand-100 p-10 text-center text-sm text-slate-400">
                Belum ada objective. {{ auth?.user?.canManage ? 'Klik "+ Objective" untuk menambah.' : '' }}
            </div>

            <!-- Kosong: ada data, tapi tak ada yang cocok filter periode -->
            <div v-else-if="!objectivesTampil.length" class="bg-white rounded-2xl border border-brand-100 p-10 text-center text-sm text-slate-400">
                Tak ada objective untuk periode "{{ periodeAktif }}".
            </div>

            <!-- Daftar objective -->
            <div v-else class="space-y-4 sm:space-y-5">
                <div v-for="obj in objectivesTampil" :key="obj.id" class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4 sm:p-5">
                    <!-- Baris atas: judul + meta + aksi (menumpuk di hp) -->
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="font-bold text-slate-800">{{ obj.title }}</h2>
                                <span v-if="obj.period" class="text-[11px] px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 font-semibold">{{ obj.period }}</span>
                                <!-- Deadline di kartu; merah bila terlewat & belum tuntas -->
                                <span v-if="obj.deadline"
                                      class="text-[11px] px-2 py-0.5 rounded-full font-semibold"
                                      :class="isOverdue(obj) ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-500'">
                                    ⏳ {{ fmtTanggal(obj.deadline) }}{{ isOverdue(obj) ? ' · lewat' : '' }}
                                </span>
                                <!-- Board Kanban yang dibuat otomatis dari objective ini -->
                                <Link v-if="obj.board_key" :href="`/pipelines/kanban?category=${obj.board_key}`"
                                      class="text-[11px] px-2 py-0.5 rounded-full font-semibold bg-sky-50 text-sky-700 hover:bg-sky-100 transition">
                                    📋 Board ↗
                                </Link>
                            </div>
                            <p v-if="obj.owner" class="text-xs text-slate-400 mt-0.5">PIC: {{ obj.owner }}</p>
                            <p v-if="obj.description" class="text-sm text-slate-500 mt-1">{{ obj.description }}</p>
                        </div>
                        <div v-if="auth?.user?.canManage" class="flex items-center gap-1.5 flex-shrink-0">
                            <button @click="openEdit(obj)" class="text-xs px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Edit</button>
                            <button @click="hapus(obj)" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Hapus</button>
                        </div>
                    </div>

                    <!-- Progress keseluruhan objective -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-slate-500 font-medium">Progress</span>
                            <span class="font-bold text-slate-700 tabular-nums">{{ objPct(obj) }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full transition-all" :class="barColor(objPct(obj))" :style="{ width: objPct(obj) + '%' }"></div>
                        </div>
                    </div>

                    <!-- Key result: daftar status selesai/belum -->
                    <div v-if="obj.key_results && obj.key_results.length" class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                        <div v-for="kr in obj.key_results" :key="kr.id" class="flex items-center gap-2 text-sm">
                            <!-- Penanda status: centang hijau bila selesai -->
                            <span class="flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold"
                                  :class="kr.completed ? 'bg-emerald-500 text-white' : 'border border-slate-300 text-transparent'">✓</span>
                            <span class="flex-1 min-w-0" :class="kr.completed ? 'text-slate-400 line-through' : 'text-slate-600'">{{ kr.title }}</span>
                            <span class="text-[11px] font-semibold flex-shrink-0" :class="kr.completed ? 'text-emerald-600' : 'text-slate-400'">
                                {{ kr.completed ? 'Selesai' : 'Belum' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-3 text-xs text-slate-400 border-t border-slate-100 pt-3">Belum ada key result.</p>
                </div>
            </div>
        </div>

        <!-- Modal tambah/edit objective -->
        <ModalWrap v-if="modalOpen" width="max-w-2xl" @close="modalOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">{{ editId ? 'Edit Objective' : 'Objective Baru' }}</h2>

            <form @submit.prevent="submit" class="space-y-4 text-sm">
                <!-- Judul objective (wajib) -->
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Objective</label>
                    <input v-model="form.title" required maxlength="150"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="form.errors.title" class="text-xs text-red-600 mt-1 block">{{ form.errors.title }}</span>
                </div>

                <!-- Periode + PIC + deadline (menumpuk di hp) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Periode</label>
                        <input v-model="form.period" placeholder="mis. Q3 2026" maxlength="50"
                               class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none" />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Penanggung jawab</label>
                        <input v-model="form.owner" maxlength="100"
                               class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none" />
                    </div>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Deadline</label>
                        <input v-model="form.deadline" type="date"
                               class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none" />
                    </div>
                </div>

                <!-- Deskripsi -->
                <div>
                    <label class="block font-medium text-slate-600 mb-1">Deskripsi <span class="text-slate-300">(opsional)</span></label>
                    <textarea v-model="form.description" rows="2" maxlength="1000"
                              class="w-full border border-slate-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                </div>

                <!-- Key result: judul + status (radio) per baris -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="font-medium text-slate-600">Key Result</label>
                        <button type="button" @click="tambahBaris" class="text-xs text-brand-600 hover:text-brand-800 font-semibold">+ Tambah</button>
                    </div>
                    <div class="space-y-2">
                        <!-- Di hp menumpuk (judul di atas, status di bawah); melebar di sm+ -->
                        <div v-for="(kr, i) in form.key_results" :key="i"
                             class="flex flex-col sm:flex-row sm:items-center gap-2 border border-slate-100 rounded-xl p-2">
                            <input v-model="kr.title" placeholder="Ukuran keberhasilan" maxlength="150"
                                   class="flex-1 border border-slate-200 rounded-lg px-2.5 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <div class="flex items-center justify-between sm:justify-start gap-3 flex-shrink-0">
                                <!-- Status = radio Belum / Selesai -->
                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" :name="'kr-status-' + i" :value="false" v-model="kr.completed" class="accent-slate-400" />
                                        <span class="text-xs text-slate-500">Belum</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" :name="'kr-status-' + i" :value="true" v-model="kr.completed" class="accent-emerald-500" />
                                        <span class="text-xs text-emerald-600 font-medium">Selesai</span>
                                    </label>
                                </div>
                                <button type="button" @click="hapusBaris(i)" title="Hapus baris"
                                        class="text-slate-400 hover:text-red-600 px-1 text-lg leading-none">&times;</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!form.key_results.length" class="text-xs text-slate-400">Belum ada key result — klik "+ Tambah".</p>
                </div>

                <!-- Aksi (menumpuk di hp) -->
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                    <button type="button" @click="modalOpen = false" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition">Batal</button>
                    <button type="submit" :disabled="form.processing"
                            class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">
                        {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

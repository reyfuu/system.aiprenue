<script setup>
// Halaman KPI per kuartal, dua tab:
//   Per Board — target "berapa kartu selesai" tiap board (peran pengelola saja)
//   Per Orang — rapor kerja tiap anggota tim, basisnya PJ kartu
//
// Isinya murni operasional papan: TIDAK ada angka keuangan — itu sebabnya
// audiensnya lebih luas daripada halaman OKR (/okr), yang terkunci untuk owner
// & manager.
//
// Penyaringan "siapa boleh lihat siapa" terjadi di SERVER. `orang` yang sampai
// ke sini sudah berisi persis apa yang boleh dibaca peran ini; `scope`
// memberitahu bentuk tampilannya. Menyaring di Vue berarti nama & angka rekan
// kerja tetap terkirim dan terbaca di source halaman.
//
// Rumus angkanya sama persis dgn panel kuartal di halaman Kanban; keduanya
// memanggil KpiController::statistik(), bukan menyalin perhitungan.
//
// Kuartal dipilih lewat querystring ?q=YYYY-Qn supaya tautannya bisa dibagikan
// dan tombol back browser tetap masuk akal — bukan state lokal Vue.
import { ref, computed } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import ModalWrap from '../ModalWrap.vue';

const props = defineProps({
    quarter: Object,          // { year, quarter, key, label }
    quarterOptions: { type: Array, default: () => [] },
    range: Object,            // { start, end } — rentang tanggal kuartal
    board: { type: Array, default: null },      // null = peran ini tak berhak melihat tab Board
    total: { type: Object, default: null },     // rekap lintas board (dihitung server)
    orang: { type: Array, default: () => [] },  // sudah disaring server sesuai `scope`
    scope: { type: String, default: 'sendiri' },// 'semua' = tabel tim | 'sendiri' = rapor pribadi
    namaSaya: { type: String, default: '' },
    canManage: Boolean,
});

// ---- Tab ----
// Disimpan di querystring, bukan state lokal: tautannya bisa dibagikan dan
// tombol back browser tetap masuk akal, sejalan dgn ?q= untuk kuartal.
// Peran tanpa tab Board langsung mendarat di "Per Orang" — tab yang tak ada
// isinya tak boleh jadi tampilan pertama.
const tabAwal = new URLSearchParams(window.location.search).get('tab');
const tab = ref(props.board === null ? 'orang' : (tabAwal === 'orang' ? 'orang' : 'board'));

const gantiTab = (t) => {
    tab.value = t;
    router.get('/kpi', { q: props.quarter.key, tab: t === 'orang' ? 'orang' : undefined },
        { preserveScroll: true, preserveState: true, replace: true });
};

// Rapor pribadi: satu baris, atau null bila orang ini memang tak pegang kartu
// ber-deadline di kuartal itu.
const raporSaya = computed(() => props.orang[0] ?? null);

// Lebar bar dibatasi 100% supaya capaian 300% tak menyeruak keluar kartu;
// angka persennya sendiri tetap ditampilkan apa adanya di sebelahnya.
const barWidth = (percent) => Math.min(100, Math.max(0, Number(percent || 0))) + '%';

// Warna bar mengikuti capaian — merah bukan berarti gagal, hanya "masih jauh".
// null (target belum ditetapkan) sengaja abu-abu, bukan merah.
const barColor = (percent) => {
    if (percent === null || percent === undefined) return 'bg-slate-300';
    if (percent >= 100) return 'bg-emerald-500';
    if (percent >= 60) return 'bg-amber-500';
    return 'bg-red-500';
};

// Warna rasio ketepatan. Ambangnya 80% — sama dgn panel Kanban, supaya angka
// yang sama tak berganti warna hanya karena berpindah halaman.
const warnaRasio = (p) => (p === null || p === undefined) ? 'text-slate-400' : (p >= 80 ? 'text-emerald-600' : 'text-red-600');

const gantiKuartal = (key) => router.get('/kpi', { q: key, tab: tab.value === 'orang' ? 'orang' : undefined }, { preserveScroll: true });

// ---- Form target board ----
const modal = ref(null);                                     // board yg sedang diedit
// Field top-level (bukan form.data.x) — konvensi useForm di repo ini.
const form = useForm({ board_key: '', year: 0, quarter: 0, target_done: 0, note: '' });

const buka = (b) => {
    modal.value = b;
    form.board_key = b.key;
    form.year = props.quarter.year;
    form.quarter = props.quarter.quarter;
    form.target_done = b.target;
    form.note = b.note || '';
    form.clearErrors();
};

const simpan = () => form.post('/kpi/targets', {
    preserveScroll: true,
    onSuccess: () => { modal.value = null; },
});

// Tautan ke Kanban board terkait, kuartal ikut dibawa supaya yang dibuka
// persis periode yang sedang ditinjau.
const urlKanban = (b) => `/pipelines/kanban?category=${encodeURIComponent(b.key)}&q=${props.quarter.key}`;
</script>

<template>
    <Layout :title="scope === 'semua' ? 'KPI' : 'Rapor Saya'">
        <!-- Header: judul + pemilih kuartal. Rentang tanggalnya ditulis eksplisit
             supaya tak ada tebak-tebakan soal batas kuartal. -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ scope === 'semua' ? 'KPI &amp; KINERJA' : 'RAPOR SAYA' }}</h1>
                    <p class="text-brand-100 text-sm">{{ quarter.label }} · {{ scope === 'semua' ? range.start + ' s/d ' + range.end : namaSaya }}</p>
                </div>
                <select
                    :value="quarter.key"
                    class="bg-white/15 border border-white/30 rounded-xl px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white/50"
                    @change="gantiKuartal($event.target.value)"
                >
                    <option v-for="o in quarterOptions" :key="o.key" :value="o.key" class="text-slate-700">{{ o.label }}</option>
                </select>
            </div>
        </header>

        <div class="p-6">
            <!-- Tab. Hanya dirender kalau peran ini memang punya dua tab —
                 tab tunggal bukan pilihan, cuma hiasan. -->
            <div v-if="board !== null" class="flex gap-1 border-b border-brand-100 mb-5">
                <button v-for="t in [{ key: 'board', label: 'Per Board' }, { key: 'orang', label: 'Per Orang' }]" :key="t.key"
                        :class="['text-sm font-semibold px-4 py-2 -mb-px border-b-2 transition',
                                 tab === t.key ? 'text-brand-700 border-brand-600' : 'text-slate-400 border-transparent hover:text-slate-600']"
                        @click="gantiTab(t.key)">
                    {{ t.label }}
                </button>
            </div>

            <!-- ================= Tab: Per Board ================= -->
            <div v-if="tab === 'board' && board !== null">
                <!-- Ringkasan lintas board: pertanyaan "tim ini sering telat atau
                     tidak" dijawab satu angka di paling atas. -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                    <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                        <p class="text-xs text-slate-400">Selesai / Target</p>
                        <p class="text-2xl font-bold text-brand-700 mt-1">{{ total.done }}<span class="text-base text-slate-400">/{{ total.target > 0 ? total.target : '—' }}</span></p>
                    </div>
                    <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Kartu kuartal ini</p><p class="text-2xl font-bold text-slate-700 mt-1">{{ total.total }}</p></div>
                    <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Tepat waktu</p><p class="text-2xl font-bold text-emerald-600 mt-1">{{ total.tepat }}</p></div>
                    <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Terlambat</p><p class="text-2xl font-bold text-red-600 mt-1">{{ total.terlambat }}</p></div>
                    <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm">
                        <p class="text-xs text-slate-400">Rasio tepat waktu</p>
                        <p class="text-2xl font-bold text-brand-700 mt-1">{{ total.persen_tepat === null ? '—' : total.persen_tepat + '%' }}</p>
                    </div>
                </div>

                <p class="text-xs text-slate-400 mb-3">
                    Kartu masuk kuartal berdasarkan <b>deadline</b>-nya. Kartu tanpa deadline tidak dihitung di sini —
                    target kuartalan hanya bermakna untuk pekerjaan yang punya batas waktu.
                </p>

                <p v-if="!board.length" class="text-center text-sm text-slate-400 py-16">Belum ada board Kanban.</p>

                <div v-else class="grid xl:grid-cols-2 gap-4">
                    <article v-for="b in board" :key="b.key" class="bg-white border border-brand-100 rounded-2xl shadow-sm p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="font-bold text-lg text-slate-700">{{ b.name }}</h2>
                            <div class="flex items-center gap-3 shrink-0">
                                <Link :href="urlKanban(b)" class="text-xs font-semibold text-slate-400 hover:text-brand-700">Buka Kanban →</Link>
                                <button v-if="canManage" class="text-xs font-semibold text-brand-700 hover:underline" @click="buka(b)">Atur target</button>
                            </div>
                        </div>

                        <div class="mt-4 flex items-end justify-between">
                            <div>
                                <span class="text-3xl font-bold text-brand-700">{{ b.done }}</span>
                                <span class="text-xs text-slate-400 ml-2">selesai dari target {{ b.target > 0 ? b.target : '—' }}</span>
                            </div>
                            <!-- Persen null = target belum ditetapkan. Sengaja BUKAN 0%:
                                 dua keadaan itu beda arti, lihat KpiController. -->
                            <span v-if="b.percent !== null" class="text-sm font-bold text-slate-600">{{ b.percent }}%</span>
                            <span v-else class="text-[11px] text-slate-400">belum ada target</span>
                        </div>
                        <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden mt-2">
                            <div :class="['h-full rounded-full transition-all', barColor(b.percent)]" :style="{ width: barWidth(b.percent) }"></div>
                        </div>

                        <!-- Analitik ketepatan per board. 'Lewat deadline' dipisah dari
                             'terlambat' karena belum final — masih bisa diselamatkan. -->
                        <div class="grid grid-cols-4 gap-2 mt-4 text-center">
                            <div class="rounded-xl bg-slate-50 p-2"><p class="text-lg font-bold text-slate-700">{{ b.total }}</p><p class="text-[10px] text-slate-500">Kartu Q ini</p></div>
                            <div class="rounded-xl bg-emerald-50 p-2"><p class="text-lg font-bold text-emerald-700">{{ b.ketepatan.tepat }}</p><p class="text-[10px] text-emerald-600">Tepat waktu</p></div>
                            <div class="rounded-xl bg-red-50 p-2"><p class="text-lg font-bold text-red-700">{{ b.ketepatan.terlambat }}</p><p class="text-[10px] text-red-600">Terlambat</p></div>
                            <div class="rounded-xl bg-amber-50 p-2"><p class="text-lg font-bold text-amber-700">{{ b.ketepatan.lewat }}</p><p class="text-[10px] text-amber-600">Lewat deadline</p></div>
                        </div>

                        <p class="text-xs text-slate-400 mt-3">
                            Rasio tepat waktu:
                            <b :class="b.ketepatan.persen_tepat === null ? 'text-slate-400' : (b.ketepatan.persen_tepat >= 80 ? 'text-emerald-600' : 'text-red-600')">
                                {{ b.ketepatan.persen_tepat === null ? 'belum bisa dinilai' : b.ketepatan.persen_tepat + '%' }}
                            </b>
                        </p>
                        <p v-if="b.note" class="text-xs text-slate-400 mt-2 border-t border-slate-100 pt-2">{{ b.note }}</p>
                        <p v-if="b.set_by" class="text-[10px] text-slate-300 mt-1">target ditetapkan {{ b.set_by }}</p>
                    </article>
                </div>
            </div>

            <!-- ================= Tab: Per Orang ================= -->
            <div v-else>
                <!-- Bentuknya berbeda, bukan cuma jumlah barisnya: satu baris
                     dalam tabel membaca seperti daftar yang gagal memuat. -->
                <template v-if="scope === 'semua'">
                    <p class="text-xs text-slate-400 mb-3">
                        Dihitung dari kartu yang <b>ditugaskan</b> ke tiap orang, dikelompokkan lewat deadline.
                        Yang terlambat paling banyak ada di atas.
                    </p>

                    <p v-if="!orang.length" class="text-center text-sm text-slate-400 py-16">
                        Belum ada kartu ber-deadline di {{ quarter.label }}.
                    </p>

                    <div v-else class="overflow-x-auto bg-white border border-brand-100 rounded-2xl shadow-sm">
                        <table class="w-full text-sm min-w-[720px]">
                            <thead>
                                <tr class="text-left text-[10px] uppercase tracking-widest text-slate-400 border-b border-slate-100 bg-slate-50">
                                    <th class="px-4 py-3 font-semibold">Nama</th>
                                    <th class="px-4 py-3 font-semibold">Peran</th>
                                    <th class="px-4 py-3 font-semibold text-right">Kartu</th>
                                    <th class="px-4 py-3 font-semibold text-right">Selesai</th>
                                    <th class="px-4 py-3 font-semibold text-right">Tepat</th>
                                    <th class="px-4 py-3 font-semibold text-right">Telat</th>
                                    <th class="px-4 py-3 font-semibold text-right">Rata-rata telat</th>
                                    <th class="px-4 py-3 font-semibold text-right">Rasio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Baris "Belum ditugaskan" (user_id null) sengaja
                                     dibedakan: ia bukan orang & tak layak dibaca
                                     sebagai rapor seseorang. -->
                                <tr v-for="b in orang" :key="b.user_id ?? 'tanpa-pj'"
                                    :class="['border-b border-slate-50 last:border-b-0', b.user_id === null ? 'bg-slate-50/70' : '']">
                                    <td class="px-4 py-3">
                                        <span :class="b.user_id === null ? 'text-slate-400 italic' : 'font-semibold text-slate-700'">{{ b.nama }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ b.role ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ b.total }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ b.selesai }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-emerald-600 font-semibold">{{ b.tepat }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-red-600 font-semibold">{{ b.terlambat }}</td>
                                    <!-- null = tak pernah telat. '—', bukan '0 hari':
                                         dua hal itu beda arti. -->
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ b.rata_telat === null ? '—' : b.rata_telat + ' hari' }}</td>
                                    <td :class="['px-4 py-3 text-right tabular-nums font-bold', warnaRasio(b.persen_tepat)]">
                                        {{ b.persen_tepat === null ? '—' : b.persen_tepat + '%' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- Rapor pribadi untuk peran non-manajemen. -->
                <template v-else>
                    <p v-if="!raporSaya" class="text-center text-sm text-slate-400 py-16">
                        Kamu belum punya kartu ber-deadline di {{ quarter.label }}.
                    </p>

                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Kartu saya</p><p class="text-2xl font-bold text-brand-700 mt-1">{{ raporSaya.total }}</p></div>
                            <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Selesai</p><p class="text-2xl font-bold text-slate-700 mt-1">{{ raporSaya.selesai }}</p></div>
                            <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Tepat waktu</p><p class="text-2xl font-bold text-emerald-600 mt-1">{{ raporSaya.tepat }}</p></div>
                            <div class="bg-white border border-brand-100 rounded-2xl p-4 shadow-sm"><p class="text-xs text-slate-400">Terlambat</p><p class="text-2xl font-bold text-red-600 mt-1">{{ raporSaya.terlambat }}</p></div>
                        </div>

                        <div class="bg-white border border-brand-100 rounded-2xl shadow-sm p-5 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500">Rasio tepat waktu</span>
                                <b :class="['text-sm font-bold', warnaRasio(raporSaya.persen_tepat)]">
                                    {{ raporSaya.persen_tepat === null ? 'belum bisa dinilai' : raporSaya.persen_tepat + '%' }}
                                </b>
                            </div>
                            <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                <div :class="['h-full rounded-full transition-all', barColor(raporSaya.persen_tepat)]" :style="{ width: barWidth(raporSaya.persen_tepat) }"></div>
                            </div>
                            <p class="text-xs text-slate-400">
                                {{ raporSaya.rata_telat === null
                                    ? 'Belum pernah terlambat di kuartal ini.'
                                    : 'Kalau terlambat, rata-rata ' + raporSaya.rata_telat + ' hari.' }}
                                <span v-if="raporSaya.lewat > 0"> · {{ raporSaya.lewat }} kartu sudah lewat deadline dan belum selesai.</span>
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ================= Modal target board ================= -->
        <ModalWrap v-if="modal" @close="modal = null">
            <h3 class="text-lg font-bold text-slate-700">Target {{ modal.name }} — {{ quarter.label }}</h3>
            <p class="text-xs text-slate-400 mt-1">Berapa kartu harus selesai di kuartal ini.</p>

            <form class="mt-4 space-y-3" @submit.prevent="simpan">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Target kartu selesai</label>
                    <input v-model="form.target_done" type="number" min="0" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300" />
                    <p v-if="form.errors.target_done" class="text-xs text-red-600 mt-1">{{ form.errors.target_done }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Catatan <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea v-model="form.note" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300"></textarea>
                    <p v-if="form.errors.note" class="text-xs text-red-600 mt-1">{{ form.errors.note }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-700" @click="modal = null">Batal</button>
                    <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-xl disabled:opacity-50">Simpan</button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

<script setup>
// Halaman Insight — performa konten Instagram & YouTube berdampingan.
// Fokusnya menjawab "konten mana yang menang & kenapa", bukan memajang
// sebanyak mungkin angka. Metrik yang tak menjawab itu sengaja tak ada.
import { router } from '@inertiajs/vue3';   // router untuk ganti filter platform
import Layout from '../Layout.vue';         // Layout sudah render sidebar + toast

const props = defineProps({
    platforms: { type: Object, default: () => ({}) },  // key→label (instagram, youtube)
    aktif: { type: String, default: 'semua' },         // filter platform aktif
    ringkasan: { type: Object, default: () => ({}) },  // kartu atas
    konten: { type: Array, default: () => [] },        // top content, sudah terurut skor
    akun: { type: Array, default: () => [] },          // snapshot harian akun
});

// Ganti filter platform. preserveState:false — skor dihitung ulang terhadap
// kumpulan yang baru, jadi seluruh angka halaman memang harus datang lagi.
const gantiPlatform = (nilai) => router.get('/insight',
    nilai === 'semua' ? {} : { platform: nilai },
    { preserveScroll: true, preserveState: false },
);

// Angka besar → ringkas (12.400 → 12,4rb). Tabel jadi terbaca tanpa menggeser.
const ringkas = (n) => {
    const v = Number(n || 0);
    if (v >= 1_000_000) return (v / 1_000_000).toFixed(1).replace('.0', '') + 'jt';
    if (v >= 1_000) return (v / 1_000).toFixed(1).replace('.0', '') + 'rb';
    return v.toLocaleString('id-ID');
};

// Persen: null berarti TAK TERHITUNG (basisnya tak ada), bukan nol.
// Dibedakan supaya "belum bisa dihitung" tak terbaca sebagai "performanya 0%".
const persen = (n) => (n === null || n === undefined ? '—' : Number(n).toFixed(1) + '%');

// Warna badge platform
const WARNA = {
    instagram: 'bg-pink-50 text-pink-700 border-pink-200',
    youtube: 'bg-red-50 text-red-700 border-red-200',
};
</script>

<template>
    <Layout title="Insight">
        <!-- Header gradient, seragam dengan halaman lain -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">INSIGHT</h1>
                <p class="text-brand-100 text-sm">Performa konten Instagram &amp; YouTube — konten mana yang menang, dan kenapa</p>
            </div>
        </header>

        <div class="px-6 py-6 space-y-6">
            <!-- ===== Filter platform ===== -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    @click="gantiPlatform('semua')"
                    :class="['text-sm font-medium px-3 py-1.5 rounded-xl border transition',
                             aktif === 'semua' ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-600 border-brand-100 hover:border-brand-300']"
                >Semua</button>
                <button
                    v-for="(label, key) in platforms" :key="key"
                    @click="gantiPlatform(key)"
                    :class="['text-sm font-medium px-3 py-1.5 rounded-xl border transition',
                             aktif === key ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-600 border-brand-100 hover:border-brand-300']"
                >{{ label }}</button>

                <span v-if="aktif !== 'semua'" class="text-xs text-slate-400 ml-1">
                    Skor dihitung ulang terhadap konten {{ platforms[aktif] }} saja
                </span>
            </div>

            <!-- ===== Kartu ringkasan. Lima, bukan sepuluh — tiap kartu menjawab
                 satu pertanyaan: jangkauan, kualitas konsumsi, atau pertumbuhan. ===== -->
            <div class="grid grid-cols-2 sm:grid-cols-3 2xl:grid-cols-5 gap-3">
                <div class="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                    <p class="text-xs text-brand-100 font-medium">Total Views</p>
                    <p class="text-2xl font-bold mt-1">{{ ringkas(ringkasan.views) }}</p>
                    <p class="text-[10px] text-brand-100 mt-0.5">{{ ringkasan.konten }} konten</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Reach</p>
                    <p class="text-xl font-bold text-brand-700 mt-1">{{ ringkas(ringkasan.reach) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Instagram</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Watch Time</p>
                    <p class="text-xl font-bold text-brand-700 mt-1">{{ ringkas(ringkasan.watchJam) }} jam</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">YouTube</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Engagement Rate</p>
                    <p class="text-xl font-bold text-emerald-600 mt-1">{{ persen(ringkasan.engagement) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">interaksi ÷ jangkauan</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                    <p class="text-xs text-slate-500 font-medium">Follower Baru</p>
                    <p class="text-xl font-bold text-brand-700 mt-1">+{{ ringkas(ringkasan.followerGain) }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">dari konten</p>
                </div>
            </div>

            <!-- ===== Top Content — inti halaman ini ===== -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <div class="flex items-baseline justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-slate-700">Top Content</h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Diperingkat <strong>content score</strong> — views 30% · engagement 25% ·
                            share 20% · save/watch 15% · follower 10%
                        </p>
                    </div>
                </div>

                <!-- Keadaan kosong. Halaman ini memang belum punya sumber data:
                     agen pengirim belum dibuat. Katakan apa adanya & tunjukkan
                     langkah berikutnya, jangan biarkan tabel kosong tanpa kata. -->
                <div v-if="!konten.length" class="py-12 text-center">
                    <p class="text-sm font-medium text-slate-500">Belum ada data konten.</p>
                    <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                        Halaman ini menunggu kiriman dari agen di VPS. Langkah pemasangan
                        ada di <code class="text-brand-600">docs/insight-instagram-youtube.md</code>.
                    </p>
                </div>

                <!-- overflow-x-auto: tabel ini lebar; biarkan tabelnya yang menggeser,
                     bukan seluruh halaman. -->
                <div v-else class="overflow-x-auto -mx-1">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-slate-400 border-b border-brand-50">
                                <th class="text-left font-medium px-2 pb-2 w-8">#</th>
                                <th class="text-left font-medium px-2 pb-2">Konten</th>
                                <th class="text-right font-medium px-2 pb-2">Views</th>
                                <th class="text-right font-medium px-2 pb-2">Eng.</th>
                                <th class="text-right font-medium px-2 pb-2">Share</th>
                                <th class="text-right font-medium px-2 pb-2">Save / Watch</th>
                                <th class="text-right font-medium px-2 pb-2">Follower</th>
                                <th class="text-right font-medium px-2 pb-2">Skor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(k, i) in konten" :key="k.id" class="border-b border-brand-50 last:border-0 hover:bg-brand-50/40">
                                <td class="px-2 py-2.5 text-slate-400 tabular-nums">{{ i + 1 }}</td>
                                <td class="px-2 py-2.5 max-w-md">
                                    <div class="flex items-center gap-2">
                                        <span :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded border shrink-0', WARNA[k.platform]]">
                                            {{ platforms[k.platform] }}
                                        </span>
                                        <!-- truncate wajar di sini: ini teks, bukan angka -->
                                        <a v-if="k.url" :href="k.url" target="_blank" rel="noopener"
                                           class="font-medium text-slate-700 hover:text-brand-700 truncate">{{ k.judul || '(tanpa judul)' }}</a>
                                        <span v-else class="font-medium text-slate-700 truncate">{{ k.judul || '(tanpa judul)' }}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-0.5">{{ k.tipe || '—' }} · {{ k.terbit || '—' }}</p>
                                </td>
                                <td class="px-2 py-2.5 text-right font-semibold text-slate-700 tabular-nums">{{ ringkas(k.views) }}</td>
                                <td class="px-2 py-2.5 text-right text-slate-600 tabular-nums">{{ persen(k.engagementRate) }}</td>
                                <td class="px-2 py-2.5 text-right text-slate-600 tabular-nums">{{ persen(k.shareRate) }}</td>
                                <!-- Satu kolom, dua arti: IG pakai save rate, YouTube pakai
                                     % ditonton. Dua ukuran berbeda yang menjawab pertanyaan
                                     sama — "kontennya dianggap berharga atau tidak". -->
                                <td class="px-2 py-2.5 text-right text-slate-600 tabular-nums">
                                    {{ k.platform === 'youtube' ? persen(k.watchPersen) : persen(k.saveRate) }}
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums"
                                    :class="k.followerGain > 0 ? 'text-emerald-600 font-semibold' : 'text-slate-400'">
                                    {{ k.followerGain > 0 ? '+' + k.followerGain : (k.followerGain ?? '—') }}
                                </td>
                                <td class="px-2 py-2.5 text-right">
                                    <span class="inline-block min-w-[3rem] text-xs font-bold px-2 py-1 rounded-lg bg-brand-50 text-brand-700 tabular-nums">
                                        {{ k.skor }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Batas keyakinan skor, ditulis di tempat orang membacanya —
                         bukan disembunyikan di dokumentasi. Skor relatif yang
                         disangka absolut menghasilkan kesimpulan yang salah. -->
                    <p class="text-[11px] text-slate-400 mt-4 leading-relaxed">
                        <strong>Cara membaca skor:</strong> nilainya <em>relatif</em> terhadap konten yang
                        sedang ditampilkan — 100 berarti "terbaik di antara ini", bukan "sempurna".
                        Membandingkan skor antar periode atau antar filter tidak sah.
                    </p>
                </div>
            </div>

            <!-- ===== Pertumbuhan akun ===== -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <h2 class="text-sm font-bold text-slate-700">Pertumbuhan Akun</h2>
                <p class="text-xs text-slate-400 mt-0.5">Follower &amp; kunjungan profil, 60 hari terakhir</p>

                <div v-if="!akun.length" class="py-8 text-center text-sm text-slate-400">
                    Belum ada snapshot harian akun.
                </div>
                <!-- Grafik menyusul setelah ada data sungguhan: bentuk grafik yang
                     dirancang tanpa melihat sebaran datanya hampir selalu salah pilih
                     skala & sumbu. Sementara ini tabel ringkas dulu. -->
                <div v-else class="overflow-x-auto mt-3">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-slate-400 border-b border-brand-50">
                                <th class="text-left font-medium px-2 pb-2">Tanggal</th>
                                <th class="text-left font-medium px-2 pb-2">Akun</th>
                                <th class="text-right font-medium px-2 pb-2">Follower</th>
                                <th class="text-right font-medium px-2 pb-2">Kunjungan Profil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(a, i) in akun" :key="i" class="border-b border-brand-50 last:border-0">
                                <td class="px-2 py-2 text-slate-500 tabular-nums">{{ a.tanggal }}</td>
                                <td class="px-2 py-2 text-slate-700">{{ a.namaAkun || a.platform }}</td>
                                <td class="px-2 py-2 text-right font-semibold text-slate-700 tabular-nums">{{ ringkas(a.followers) }}</td>
                                <td class="px-2 py-2 text-right text-slate-600 tabular-nums">{{ ringkas(a.profileViews) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </Layout>
</template>

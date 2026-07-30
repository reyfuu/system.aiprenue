<script setup>
// Halaman Upload — publikasi konten ke TikTok, YouTube, Instagram.
// TEMPLATE: baru YouTube yang jalur uploadnya akan aktif lebih dulu; TikTok & IG
// masih placeholder ("Segera"). Belum ada backend upload — lihat // TODO di bawah.
import { ref, computed } from 'vue';
import Layout from '../Layout.vue';

const props = defineProps({
    platforms: { type: Array, default: () => [] },   // { key, name, status: 'ready'|'soon' }
});

// Platform terpilih (default: yang 'ready' saja). 'soon' tak bisa dipilih.
const dipilih = ref(props.platforms.filter((p) => p.status === 'ready').map((p) => p.key));
const togglePlatform = (p) => {
    if (p.status !== 'ready') return;
    const i = dipilih.value.indexOf(p.key);
    i === -1 ? dipilih.value.push(p.key) : dipilih.value.splice(i, 1);
};

const form = ref({ judul: '', deskripsi: '', file: null, jadwal: '' });
const bisaKirim = computed(() => dipilih.value.length && form.value.judul.trim() && form.value.file);

// TODO: sambungkan ke endpoint upload sungguhan. Sesuai arsitektur Insight,
// eksekusinya di agen VPS (pegang OAuth youtube.upload) — halaman ini nanti
// mengirim draft ke sana. Sementara template: tombol cuma memberi info.
const kirim = () => {
    alert('Masih template — jalur upload belum aktif. YouTube yang menyusul lebih dulu.');
};
</script>

<template>
    <Layout title="Upload">
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5">
                <h1 class="text-2xl font-bold tracking-tight">UPLOAD</h1>
                <p class="text-brand-100 text-sm">Publikasikan konten ke TikTok, YouTube &amp; Instagram dari satu tempat</p>
            </div>
        </header>

        <div class="px-6 py-6 max-w-2xl">
            <!-- Banner template — jujur soal status biar tak disangka sudah jalan. -->
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <strong>Masih template.</strong> Jalur upload belum aktif. <strong>YouTube</strong> yang akan bisa dicoba lebih dulu; TikTok &amp; Instagram menyusul setelah izin/OAuth siap.
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5 space-y-5">
                <!-- Pilih platform -->
                <div>
                    <p class="text-sm font-medium text-slate-600 mb-2">Publikasikan ke</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="p in platforms" :key="p.key" type="button"
                            @click="togglePlatform(p)" :disabled="p.status !== 'ready'"
                            :class="['px-4 py-2 rounded-xl border text-sm font-semibold transition flex items-center gap-2',
                                     p.status !== 'ready' ? 'opacity-50 cursor-not-allowed border-slate-200 text-slate-400 bg-slate-50'
                                     : dipilih.includes(p.key) ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-300'
                                     : 'border-slate-200 text-slate-600 hover:bg-slate-50']">
                            {{ p.name }}
                            <span v-if="p.status !== 'ready'" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-500">Segera</span>
                            <span v-else-if="dipilih.includes(p.key)">✓</span>
                        </button>
                    </div>
                </div>

                <label class="block text-sm">
                    <span class="font-medium text-slate-600">Judul</span>
                    <input v-model="form.judul" placeholder="Judul konten…" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>

                <label class="block text-sm">
                    <span class="font-medium text-slate-600">Deskripsi / caption</span>
                    <textarea v-model="form.deskripsi" rows="4" placeholder="Caption, hashtag, deskripsi…" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                </label>

                <div class="text-sm">
                    <p class="font-medium text-slate-600 mb-1">File video</p>
                    <input id="up-file" type="file" accept="video/*" @change="form.file = $event.target.files[0]" class="hidden" />
                    <div class="flex items-center gap-2">
                        <label for="up-file" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-semibold transition">Pilih video</label>
                        <span class="flex-1 text-slate-500 truncate">{{ form.file ? form.file.name : 'Belum ada file dipilih' }}</span>
                    </div>
                </div>

                <label class="block text-sm">
                    <span class="font-medium text-slate-600">Jadwal <span class="font-normal text-slate-400">(opsional — kosong = publish sekarang)</span></span>
                    <input v-model="form.jadwal" type="datetime-local" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>

                <div class="flex items-center justify-between pt-1">
                    <p class="text-xs text-slate-400">{{ dipilih.length }} platform dipilih</p>
                    <button type="button" @click="kirim" :disabled="!bisaKirim"
                        class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-50">
                        Upload
                    </button>
                </div>
            </div>
        </div>
    </Layout>
</template>

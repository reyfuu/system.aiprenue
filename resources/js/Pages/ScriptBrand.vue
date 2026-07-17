<script setup>
// Daftar naskah satu brand, dikelompokkan per paket (tanggal).
// Pengelompokannya dikerjakan ScriptController@show — halaman ini tinggal render.
import { ref, reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import ModalWrap from '../ModalWrap.vue';   // pembungkus modal (sama spt Kanban/Order)

// Props dari ScriptController@show
const props = defineProps({
    brand: Object,                                  // { key, name }
    packs: { type: Array, default: () => [] },      // [{ date, label, pdf, items: [{id,title,body}] }]
    filters: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },   // hanya manajemen boleh hapus
});

// ---- Cari ----
const f = reactive({ search: props.filters.search || '' });
const applyFilters = () => router.get(`/script/${props.brand.key}`, { ...f }, { preserveState: true, replace: true });

// Total naskah lintas paket — untuk teks ringkasan di atas
const total = computed(() => props.packs.reduce((s, p) => s + p.items.length, 0));

// ---- Modal detail ----
const detail = ref(null);   // naskah yang sedang dibuka (null = tertutup)

// Salin naskah ke clipboard. Ini alasan utama naskahnya disimpan di DB & bukan
// cuma jadi tautan Drive: tim tinggal salin-tempel ke aplikasi editing.
const salinOk = ref(false);
const salin = async (body) => {
    try {
        await navigator.clipboard.writeText(body);
        salinOk.value = true;
        setTimeout(() => (salinOk.value = false), 1500);
    } catch {
        salinOk.value = false;   // clipboard diblokir (http non-lokal) — biarkan diam
    }
};

const hapus = (s) => {
    if (!props.canManage) return;
    if (!confirm(`Hapus naskah "${s.title}"? Tindakan ini tidak bisa dibatalkan.`)) return;
    router.delete('/script/' + s.id, { preserveScroll: true, onSuccess: () => (detail.value = null) });
};
</script>

<template>
    <Layout :title="'Script — ' + brand.name">
        <!-- Header + jalan pulang ke galeri -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center gap-3">
                <Link href="/script" title="Semua brand" class="text-brand-100 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ brand.name.toUpperCase() }}</h1>
                    <p class="text-brand-100 text-sm">{{ total }} naskah · {{ packs.length }} paket</p>
                </div>
            </div>
        </header>

        <div class="px-6 py-6 space-y-5">
            <!-- Cari -->
            <form @submit.prevent="applyFilters" class="flex items-center gap-2 text-sm">
                <input v-model="f.search" placeholder="Cari judul atau isi naskah…"
                       class="flex-1 max-w-md border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">Cari</button>
                <Link v-if="filters.search" :href="`/script/${brand.key}`" class="text-brand-600 hover:text-brand-800 px-2 font-medium">Reset</Link>
            </form>

            <!-- Kosong -->
            <p v-if="!packs.length" class="text-sm text-slate-400 py-12 text-center">
                {{ filters.search ? 'Tak ada naskah yang cocok.' : 'Belum ada naskah untuk brand ini.' }}
            </p>

            <!-- Satu blok per paket (tanggal) -->
            <section v-for="p in packs" :key="p.date" class="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <div class="flex items-baseline justify-between gap-3 mb-3">
                    <div class="flex items-baseline gap-2">
                        <h2 class="text-sm font-bold text-slate-700">{{ p.label }}</h2>
                        <span class="text-xs text-slate-400">{{ p.items.length }} naskah</span>
                    </div>
                    <!-- Unduh paket utuh sbg PDF. Tautan biasa, bukan Inertia:
                         responsnya file, bukan halaman — router.get() akan
                         menunggu properti Inertia yang tak pernah datang. -->
                    <a :href="p.pdf"
                       class="text-xs font-semibold text-brand-600 hover:text-brand-800">Unduh PDF ↓</a>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <button v-for="s in p.items" :key="s.id" type="button" @click="detail = s"
                            class="text-left bg-slate-50 hover:bg-brand-50 border border-slate-100 hover:border-brand-200 rounded-xl px-3 py-2.5 transition">
                        <p class="text-sm font-semibold text-slate-700 line-clamp-2">{{ s.title }}</p>
                        <p class="text-xs text-slate-400 line-clamp-1 mt-0.5">{{ s.body }}</p>
                    </button>
                </div>
            </section>
        </div>

        <!-- Modal detail naskah -->
        <ModalWrap v-if="detail" width="max-w-2xl" align="items-start" @close="detail = null">
            <div class="flex items-start justify-between gap-3 mb-4">
                <h2 class="text-lg font-bold text-brand-800">{{ detail.title }}</h2>
                <button type="button" @click="detail = null" class="text-slate-400 hover:text-slate-600 text-xl leading-none flex-shrink-0">&times;</button>
            </div>
            <!-- whitespace-pre-line: naskah datang dgn baris baru yang bermakna -->
            <div class="text-sm text-slate-700 whitespace-pre-line max-h-[60vh] overflow-y-auto bg-slate-50 rounded-xl p-4">{{ detail.body }}</div>
            <div class="flex justify-end gap-2 mt-4">
                <button v-if="canManage" type="button" @click="hapus(detail)"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-red-600 hover:bg-red-50 text-sm font-semibold transition">Hapus</button>
                <button type="button" @click="salin(detail.body)"
                        class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition">
                    {{ salinOk ? 'Tersalin ✓' : 'Salin naskah' }}
                </button>
            </div>
        </ModalWrap>
    </Layout>
</template>

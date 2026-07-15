<script setup>
// Kanban LUAR — galeri semua board dikelompokkan per "section".
// Klik kartu board → masuk kanban DALAM (/pipelines/kanban?category=key).
import { ref, computed } from 'vue';                         // reaktivitas
import { Link, useForm, router } from '@inertiajs/vue3';     // Link nav, form, aksi
import Layout from '../Layout.vue';                          // kerangka + sidebar
import ModalWrap from '../ModalWrap.vue';                    // pembungkus modal

const props = defineProps({ boards: Array, canManage: Boolean });

// Kelompokkan board per section → { 'Billy Expense': [board, ...], ... }
const grouped = computed(() => {
    const g = {};
    for (const b of props.boards) (g[b.section] ??= []).push(b);
    return g;
});

// ---- Buat board (nama + section) ----
const createOpen = ref(false);
const createForm = useForm({ name: '', section: '' });
const openCreate = () => { createForm.reset(); createOpen.value = true; };
// reset() WAJIB di dalam onSuccess, bukan cuma di openCreate(): Inertia v3 menjadikan
// data yang barusan dikirim sbg `defaults` baru setelah submit sukses, jadi reset()
// di openCreate() malah memunculkan nama board sebelumnya. Callback ini jalan SEBELUM
// Inertia menyimpan defaults barunya, jadi yang tertangkap = form kosong.
const submitCreate = () => createForm.post('/boards', {
    onSuccess: () => { createOpen.value = false; createForm.reset(); },
});

// ---- Ubah board ----
const editOpen = ref(false);
const editKey = ref(null);
const editForm = useForm({ name: '', section: '' });
const openEdit = (b) => {
    editKey.value = b.key;
    editForm.name = b.name;
    editForm.section = b.section === 'Tanpa Grup' ? '' : b.section; // 'Tanpa Grup' = section null
    editOpen.value = true;
};
const submitEdit = () => editForm.put('/boards/' + editKey.value, { onSuccess: () => (editOpen.value = false) });

// ---- Hapus board ----
const deleteBoard = (b) => { if (confirm(`Hapus board "${b.name}"? (hanya bila kosong)`)) router.delete('/boards/' + b.key); };

const menuOpen = ref(null);                                  // key board yg menunya terbuka
</script>

<template>
    <Layout title="Kanban">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-brand-800">List Trello</h1>
                    <p class="text-sm text-slate-400">{{ boards.length }} board · klik untuk membuka</p>
                </div>
                <button v-if="canManage" @click="openCreate" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Board Baru
                </button>
            </div>

            <!-- Empty state -->
            <div v-if="boards.length === 0" class="bg-white border border-dashed border-brand-200 rounded-2xl p-12 text-center">
                <svg class="w-12 h-12 mx-auto text-brand-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h4v14H4zM10 5h4v9h-4zM16 5h4v6h-4z" /></svg>
                <p class="text-slate-500 font-medium">Belum ada board.</p>
                <p class="text-sm text-slate-400 mb-4">Buat board pertamamu untuk mulai menyusun task.</p>
                <button v-if="canManage" @click="openCreate" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Board Baru
                </button>
            </div>

            <!-- Grup per section -->
            <div v-for="(list, section) in grouped" :key="section" class="mb-8">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500 mb-3">{{ section }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div v-for="b in list" :key="b.key" class="relative group">
                        <!-- Kartu board (klik → kanban dalam) -->
                        <Link :href="`/pipelines/kanban?category=${b.key}`" class="block bg-white border border-brand-100 rounded-2xl shadow-sm hover:shadow-md hover:border-brand-300 transition p-5 h-full">
                            <div class="flex items-start justify-between mb-8">
                                <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-brand-50 text-brand-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h4v14H4zM10 5h4v9h-4zM16 5h4v6h-4z" /></svg>
                                </span>
                                <span v-if="b.super_admin_only" title="Board khusus" class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    privat
                                </span>
                            </div>
                            <p class="font-bold text-slate-700 leading-snug mb-1 truncate">{{ b.name }}</p>
                            <p class="text-xs text-slate-400">{{ b.count }} task</p>
                        </Link>

                        <!-- Menu ... (edit/hapus) — di atas Link -->
                        <div v-if="canManage" class="absolute top-3 right-3">
                            <button @click.stop.prevent="menuOpen = menuOpen === b.key ? null : b.key" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 opacity-0 group-hover:opacity-100 transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 12a2 2 0 11-4 0 2 2 0 014 0zm8 0a2 2 0 11-4 0 2 2 0 014 0zm8 0a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </button>
                            <div v-if="menuOpen === b.key" class="absolute right-0 top-8 z-20 w-36 bg-white border border-brand-100 rounded-xl shadow-lg py-1 text-sm">
                                <button @click.stop.prevent="menuOpen = null; openEdit(b)" class="w-full text-left px-4 py-2 hover:bg-brand-50 text-slate-600">Ubah board</button>
                                <button @click.stop.prevent="menuOpen = null; deleteBoard(b)" class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600">Hapus board</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Modal buat board ===== -->
        <ModalWrap v-if="canManage && createOpen" width="max-w-sm" @close="createOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Board Baru</h2>
            <form @submit.prevent="submitCreate" class="space-y-3 text-sm">
                <label class="block font-medium text-slate-600">Nama board
                    <input v-model="createForm.name" required autofocus placeholder="mis. HRD" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="block font-medium text-slate-600">Section / Grup (opsional)
                    <input v-model="createForm.section" placeholder="mis. Billy Expense" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span class="text-[11px] text-slate-400">Board dengan section sama dikelompokkan di galeri.</span>
                </label>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="createOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="createForm.processing" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold disabled:opacity-60">Simpan</button>
                </div>
            </form>
        </ModalWrap>

        <!-- ===== Modal ubah board ===== -->
        <ModalWrap v-if="canManage && editOpen" width="max-w-sm" @close="editOpen = false">
            <h2 class="text-lg font-bold text-brand-800 mb-4">Ubah Board</h2>
            <form @submit.prevent="submitEdit" class="space-y-3 text-sm">
                <label class="block font-medium text-slate-600">Nama board
                    <input v-model="editForm.name" required autofocus class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <label class="block font-medium text-slate-600">Section / Grup (opsional)
                    <input v-model="editForm.section" placeholder="mis. Billy Expense" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="editOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="editForm.processing" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold disabled:opacity-60">Simpan</button>
                </div>
            </form>
        </ModalWrap>
    </Layout>
</template>

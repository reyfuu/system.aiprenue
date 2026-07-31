<script setup>
/**
 * Halaman Audit Log — riwayat semua aksi mutasi di sistem.
 * Hanya bisa diakses owner & it. Read-only: tidak bisa edit/hapus.
 *
 * Filter: user, aksi, tipe model, rentang tanggal, pencarian nama entity.
 * Pagination 50 baris per halaman.
 */
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Layout from '@/Layout.vue';
import ModalWrap from '@/ModalWrap.vue';

defineProps({
    logs: Object, // LengthAwarePaginator
    users: Array, // [{id, name, role}]
    modelTypes: Array, // ['App\\Models\\Pipeline', ...]
    actions: Array, // ['create', 'update', ...]
    filters: Object, // {user_id, action, model_type, search, dari, sampai}
});

const form = useForm({
    user_id: null,
    action: null,
    model_type: null,
    search: '',
    dari: '',
    sampai: '',
});

const showDetail = ref(null);

// Warna badge per aksi — samakan bahasa visual dgn seluruh app (tint lembut).
const ACTION_BADGE = {
    create: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    update: 'bg-amber-50 text-amber-700 ring-amber-200',
    delete: 'bg-red-50 text-red-700 ring-red-200',
    archive: 'bg-purple-50 text-purple-700 ring-purple-200',
    restore: 'bg-blue-50 text-blue-700 ring-blue-200',
    progress: 'bg-blue-50 text-blue-700 ring-blue-200',
    approve: 'bg-teal-50 text-teal-700 ring-teal-200',
    reject: 'bg-orange-50 text-orange-700 ring-orange-200',
};
const actionBadge = (a) => ACTION_BADGE[a] || 'bg-slate-100 text-slate-600 ring-slate-200';

/** Short model type — tampilkan hanya nama class saja, tanpa namespace. */
function shortModel(type) {
    return type.split('\\').pop();
}

/** Format created_at jadi `dd MMM yyyy, HH:mm`. */
function fmt(ts) {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** Terapkan filter & reset halaman ke 1. */
function filter() {
    // Hanya kirim field yang terisi
    const q = {};
    for (const [k, v] of Object.entries(form)) {
        if (v !== null && v !== '' && v !== undefined) q[k] = v;
    }
    router.get('/audit', q, { preserveState: true, preserveScroll: true, replace: true });
}

function resetFilter() {
    form.user_id = null;
    form.action = null;
    form.model_type = null;
    form.search = '';
    form.dari = '';
    form.sampai = '';
    filter();
}
</script>

<template>
    <Layout title="Audit Log">
        <!-- Header + tombol reset filter -->
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h1 class="text-xl font-bold text-brand-800">Audit Log</h1>
                <p class="text-xs text-slate-400">Riwayat semua perubahan data di sistem — read-only.</p>
            </div>
            <button
                class="text-sm font-medium text-slate-500 hover:text-slate-700 border border-slate-200 rounded-lg px-3 py-1.5 hover:bg-slate-50"
                @click="resetFilter"
            >
                Reset Filter
            </button>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap gap-2 mb-4">
            <select v-model="form.user_id" class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-2 focus:ring-brand-400 outline-none" @change="filter">
                <option :value="null">Semua User</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role }})</option>
            </select>
            <select v-model="form.action" class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-2 focus:ring-brand-400 outline-none" @change="filter">
                <option :value="null">Semua Aksi</option>
                <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
            </select>
            <select v-model="form.model_type" class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-2 focus:ring-brand-400 outline-none" @change="filter">
                <option :value="null">Semua Tipe</option>
                <option v-for="m in modelTypes" :key="m" :value="m">{{ shortModel(m) }}</option>
            </select>
            <input
                v-model="form.search"
                placeholder="Cari entity…"
                class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm w-48 focus:ring-2 focus:ring-brand-400 outline-none"
                @keyup.enter="filter"
                @blur="filter"
            />
            <input v-model="form.dari" type="date" class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-400 outline-none" title="Dari tanggal" @change="filter" />
            <input v-model="form.sampai" type="date" class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-400 outline-none" title="Sampai tanggal" @change="filter" />
        </div>

        <!-- Tabel audit log -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Waktu</th>
                            <th class="px-4 py-2.5 font-semibold">User</th>
                            <th class="px-4 py-2.5 font-semibold">Aksi</th>
                            <th class="px-4 py-2.5 font-semibold">Tipe</th>
                            <th class="px-4 py-2.5 font-semibold">Entity</th>
                            <th class="px-4 py-2.5 font-semibold text-right">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">Belum ada data audit log.</td>
                        </tr>
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-2.5 whitespace-nowrap text-slate-500 tabular-nums">{{ fmt(log.created_at) }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span v-if="log.user" class="text-slate-700 font-medium">{{ log.user.name }}</span>
                                <span v-else class="text-slate-400 italic">system</span>
                                <span class="text-xs text-slate-400 ml-1">({{ log.user_role }})</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset', actionBadge(log.action)]">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-slate-500 text-xs font-mono">{{ shortModel(log.model_type) }}</td>
                            <td class="px-4 py-2.5 max-w-56 truncate text-slate-700">{{ log.model_name || 'ID ' + log.model_id }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <button
                                    v-if="log.old_values || log.new_values"
                                    class="text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline"
                                    @click="showDetail = showDetail === log.id ? null : log.id"
                                >
                                    {{ showDetail === log.id ? 'Sembunyi' : 'Lihat' }}
                                </button>
                                <span v-else class="text-xs text-slate-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="logs.last_page > 1" class="mt-4 flex justify-center">
            <div class="flex flex-wrap gap-1 text-sm">
                <button
                    v-for="page in logs.last_page"
                    :key="page"
                    :class="
                        page === logs.current_page
                            ? 'bg-brand-600 text-white'
                            : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
                    "
                    class="px-3 py-1 rounded-lg"
                    @click="router.get('/audit', { page, ...filters }, { preserveState: true, preserveScroll: true })"
                >
                    {{ page }}
                </button>
            </div>
        </div>

        <!-- Modal detail old/new values -->
        <ModalWrap v-if="showDetail !== null" width="max-w-lg" @close="showDetail = null">
            <h2 class="text-base font-bold text-brand-800 mb-3">Detail Perubahan</h2>
            <template v-if="showDetail">
                <div v-for="log in logs.data.filter((l) => l.id === showDetail)" :key="log.id" class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <h3 class="font-semibold text-slate-500 mb-2">Sebelum</h3>
                        <div v-if="log.old_values" class="space-y-0.5">
                            <div v-for="(val, key) in log.old_values" :key="key" class="flex gap-1">
                                <span class="text-slate-400 font-mono text-xs w-32 truncate">{{ key }}</span>
                                <span class="text-slate-600 break-all">{{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
                            </div>
                        </div>
                        <div v-else class="text-slate-400 italic">— (aksi create, tidak ada nilai sebelumnya)</div>
                    </div>
                    <div>
                        <h3 class="font-semibold text-emerald-700 mb-2">Sesudah</h3>
                        <div v-if="log.new_values && Object.keys(log.new_values).length" class="space-y-0.5">
                            <div v-for="(val, key) in log.new_values" :key="key" class="flex gap-1">
                                <span class="text-slate-400 font-mono text-xs w-32 truncate">{{ key }}</span>
                                <span :class="{ 'text-emerald-700': !log.old_values || log.old_values[key] !== val }" class="break-all">{{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
                            </div>
                        </div>
                        <div v-else class="text-slate-400 italic">— (aksi delete, entity dihapus)</div>
                    </div>
                </div>
            </template>
        </ModalWrap>
    </Layout>
</template>

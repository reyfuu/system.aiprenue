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

/** Format JSON jadi ringkasan: comma-separated key. */
function jsonSummary(obj) {
    if (!obj) return '';
    const keys = Object.keys(obj);
    return keys.length > 3 ? keys.slice(0, 3).join(', ') + `…(+${keys.length - 3})` : keys.join(', ');
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
            <h1 class="text-xl font-semibold text-gray-800">Audit Log</h1>
            <button class="text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded px-2 py-1" @click="resetFilter">
                Reset Filter
            </button>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap gap-2 mb-5">
            <select v-model="form.user_id" class="border border-gray-300 rounded px-2 py-1 text-sm" @change="filter">
                <option :value="null">Semua User</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role }})</option>
            </select>
            <select v-model="form.action" class="border border-gray-300 rounded px-2 py-1 text-sm" @change="filter">
                <option :value="null">Semua Aksi</option>
                <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
            </select>
            <select v-model="form.model_type" class="border border-gray-300 rounded px-2 py-1 text-sm" @change="filter">
                <option :value="null">Semua Tipe</option>
                <option v-for="m in modelTypes" :key="m" :value="m">{{ shortModel(m) }}</option>
            </select>
            <input
                v-model="form.search"
                placeholder="Cari entity…"
                class="border border-gray-300 rounded px-2 py-1 text-sm w-48"
                @keyup.enter="filter"
                @blur="filter"
            />
            <input
                v-model="form.dari"
                type="date"
                class="border border-gray-300 rounded px-2 py-1 text-sm"
                title="Dari tanggal"
                @change="filter"
            />
            <input
                v-model="form.sampai"
                type="date"
                class="border border-gray-300 rounded px-2 py-1 text-sm"
                title="Sampai tanggal"
                @change="filter"
            />
        </div>

        <!-- Tabel audit log -->
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200 rounded">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="px-3 py-2 border-b">Waktu</th>
                        <th class="px-3 py-2 border-b">User</th>
                        <th class="px-3 py-2 border-b">Aksi</th>
                        <th class="px-3 py-2 border-b">Tipe</th>
                        <th class="px-3 py-2 border-b">Entity</th>
                        <th class="px-3 py-2 border-b">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" class="px-3 py-6 text-center text-gray-400">Belum ada data audit log.</td>
                    </tr>
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 border-b border-gray-100">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ fmt(log.created_at) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <span v-if="log.user">{{ log.user.name }}</span>
                            <span v-else class="text-gray-400 italic">system</span>
                            <span class="text-xs text-gray-400 ml-1">({{ log.user_role }})</span>
                        </td>
                        <td class="px-3 py-2">
                            <span
                                :class="{
                                    'text-green-600': log.action === 'create',
                                    'text-amber-600': log.action === 'update',
                                    'text-red-600': log.action === 'delete',
                                    'text-purple-600': log.action === 'archive',
                                    'text-blue-600': log.action === 'restore' || log.action === 'progress',
                                    'text-teal-600': log.action === 'approve',
                                    'text-orange-600': log.action === 'reject',
                                }"
                                class="font-medium"
                                >{{ log.action }}</span
                            >
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-500 text-xs font-mono">{{ shortModel(log.model_type) }}</td>
                        <td class="px-3 py-2 max-w-56 truncate text-gray-700">{{ log.model_name || 'ID ' + log.model_id }}</td>
                        <td class="px-3 py-2">
                            <button
                                v-if="log.old_values || log.new_values"
                                class="text-xs text-indigo-600 hover:underline"
                                @click="showDetail = showDetail === log.id ? null : log.id"
                            >
                                {{ showDetail === log.id ? 'Sembunyi' : 'Lihat' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="logs.last_page > 1" class="mt-4 flex justify-center">
            <div class="flex gap-1 text-sm">
                <button
                    v-for="page in logs.last_page"
                    :key="page"
                    :class="
                        page === logs.current_page
                            ? 'bg-indigo-600 text-white'
                            : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'
                    "
                    class="px-3 py-1 rounded"
                    @click="router.get('/audit', { page, ...filters }, { preserveState: true, preserveScroll: true })"
                >
                    {{ page }}
                </button>
            </div>
        </div>

        <!-- Modal detail old/new values -->
        <ModalWrap :show="showDetail !== null" max-width="lg" @close="showDetail = null">
            <template #title>Detail Perubahan</template>
            <template v-if="showDetail">
                <div v-for="log in logs.data.filter((l) => l.id === showDetail)" :key="log.id" class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <h3 class="font-semibold text-gray-500 mb-2">Sebelum</h3>
                        <div v-if="log.old_values" class="space-y-0.5">
                            <div v-for="(val, key) in log.old_values" :key="key" class="flex gap-1">
                                <span class="text-gray-400 font-mono text-xs w-32 truncate">{{ key }}</span>
                                <span class="text-gray-600 break-all">{{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
                            </div>
                        </div>
                        <div v-else class="text-gray-400 italic">— (aksi create, tidak ada nilai sebelumnya)</div>
                    </div>
                    <div>
                        <h3 class="font-semibold text-green-700 mb-2">Sesudah</h3>
                        <div v-if="log.new_values" class="space-y-0.5">
                            <div v-for="(val, key) in log.new_values" :key="key" class="flex gap-1">
                                <span class="text-gray-400 font-mono text-xs w-32 truncate">{{ key }}</span>
                                <span :class="{ 'text-green-700': !log.old_values || log.old_values[key] !== val }" class="break-all">{{
                                    typeof val === 'object' ? JSON.stringify(val) : val
                                }}</span>
                            </div>
                        </div>
                        <div v-else class="text-gray-400 italic">— (aksi delete, entity dihapus)</div>
                    </div>
                </div>
            </template>
        </ModalWrap>
    </Layout>
</template>

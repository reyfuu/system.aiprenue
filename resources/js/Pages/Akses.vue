<script setup>
// Halaman Manajemen Akses: matriks centang peran × menu.
// Sumber kebenaran hak akses ada di tabel `role_menu_access` (lihat User::canSee),
// halaman ini yang mengubahnya — jadi aturan bisa diganti tanpa deploy ulang.
import { useForm } from '@inertiajs/vue3';               // useForm: kirim matriks + state processing
import Layout from '../Layout.vue';                      // layout bersama (sidebar + toast)

const props = defineProps({
    roles:    Object,   // key peran -> label ("owner" -> "Owner")
    menus:    Object,   // key menu  -> label ("kanban" -> "Kanban")
    akses:    Object,   // matriks saat ini: peran -> [menu yang dicentang]
    kelola:   Object,   // matriks menu dengan level CRUD
    terkunci: Array,    // peran yang tak bisa diubah (owner) — pagar anti-kekunci
});

// Salinan matriks untuk diedit. Wajib disalin: prop Inertia readonly, dan
// centang/hapus di bawah memutasi arraynya langsung.
// Field TOP-LEVEL `akses` (bukan form.data.akses) sesuai konvensi useForm repo ini.
const form = useForm({
    akses: Object.fromEntries(
        Object.keys(props.roles).map((r) => [r, [...(props.akses[r] || [])]]),
    ),
    kelola: Object.fromEntries(
        Object.keys(props.roles).map((r) => [r, [...(props.kelola[r] || [])]]),
    ),
});

const dikunci = (role) => props.terkunci.includes(role);          // owner: selalu penuh
const dicentang = (role, menu) => dikunci(role) || form.akses[role].includes(menu);
const izinTetap = (role, menu) => menu === 'pembukuan';
const izinTetapAktif = (role, menu) => menu === 'pembukuan' && ['owner', 'manager'].includes(role);

// Toggle satu sel. Peran terkunci diabaikan — owner selalu boleh semua, dan
// server pun membuang kiriman untuk owner (jangan andalkan UI saja).
const toggle = (role, menu) => {
    if (dikunci(role)) return;
    const list = form.akses[role];
    const i = list.indexOf(menu);
    if (i === -1) list.push(menu);
    else list.splice(i, 1);
};

// Content punya tiga level: tidak ada baris = nonaktif, ada baris biasa = lihat,
// ada baris + can_manage = CRUD. Ubah kedua array secara bersamaan.
const levelContent = (role) => {
    if (dikunci(role)) return 'crud';
    if (!form.akses[role].includes('content')) return 'off';
    return form.kelola[role].includes('content') ? 'crud' : 'view';
};
const setLevelContent = (role, level) => {
    form.akses[role] = form.akses[role].filter((menu) => menu !== 'content');
    form.kelola[role] = form.kelola[role].filter((menu) => menu !== 'content');
    if (level !== 'off') form.akses[role].push('content');
    if (level === 'crud') form.kelola[role].push('content');
};

// Centang/hapus seluruh baris peran sekaligus — 10 menu × 5 peran terlalu banyak
// untuk diklik satu-satu saat memberi peran baru akses penuh.
const toggleBaris = (role) => {
    if (dikunci(role)) return;
    const semua = Object.keys(props.menus);
    const kosongkan = form.akses[role].length === semua.length;
    form.akses[role] = kosongkan ? [] : [...semua];
    if (kosongkan) form.kelola[role] = [];
};

const simpan = () => form.put('/akses', { preserveScroll: true });
</script>

<template>
    <Layout title="Manajemen Akses">
        <!-- Header gradient brand, seragam dgn halaman lain -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">MANAJEMEN AKSES</h1>
                    <p class="text-brand-100 text-sm">Atur menu yang boleh dilihat tiap peran</p>
                </div>
                <button @click="simpan" :disabled="form.processing"
                        class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow transition disabled:opacity-60">
                    {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
                </button>
            </div>
        </header>

        <div class="p-6">
            <!-- Matriks. overflow-x-auto: 10 kolom menu lebih lebar dari layar sempit,
                 dan tabel TIDAK boleh bikin seluruh halaman geser mendatar. -->
            <div class="bg-white border border-brand-100 rounded-2xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-brand-100 bg-brand-50/60">
                            <th class="text-left font-semibold text-slate-600 px-4 py-3 sticky left-0 bg-brand-50/60">Peran</th>
                            <th v-for="(label, key) in menus" :key="key"
                                class="px-3 py-3 font-semibold text-slate-600 text-center whitespace-nowrap">{{ label }}</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(labelRole, role) in roles" :key="role" class="border-b border-brand-50 last:border-0">
                            <td class="px-4 py-3 font-semibold text-slate-700 sticky left-0 bg-white whitespace-nowrap">
                                {{ labelRole }}
                                <!-- Kenapa owner dikunci: kalau aksesnya bisa dicabut, satu centang salah
                                     bisa bikin TAK ADA yang bisa membuka halaman ini lagi. -->
                                <span v-if="dikunci(role)" class="ml-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">
                                    selalu penuh
                                </span>
                            </td>
                            <td v-for="(label, key) in menus" :key="key" class="px-3 py-3 text-center">
                                <!-- Content memakai level akses, menu lama tetap checkbox lihat/tidak. -->
                                <select v-if="key === 'content'" :value="levelContent(role)" :disabled="dikunci(role)"
                                        @change="setLevelContent(role, $event.target.value)"
                                        class="border border-slate-200 rounded-lg px-2 py-1 text-xs bg-white disabled:opacity-50">
                                    <option value="off">Nonaktif</option>
                                    <option value="view">Lihat saja</option>
                                    <option value="crud">CRUD</option>
                                </select>
                                <input v-else type="checkbox" class="accent-brand-600 w-4 h-4 disabled:opacity-40"
                                       :checked="izinTetap(role, key) ? izinTetapAktif(role, key) : dicentang(role, key)"
                                       :disabled="dikunci(role) || izinTetap(role, key)"
                                       :aria-label="`${labelRole} boleh lihat ${label}`"
                                       @change="toggle(role, key)" />
                            </td>
                            <td class="px-3 py-3 text-right">
                                <button v-if="!dikunci(role)" type="button" @click="toggleBaris(role)"
                                        class="text-xs font-medium text-brand-600 hover:text-brand-800 whitespace-nowrap">
                                    semua / kosong
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-slate-500 mt-3">
                Perubahan berlaku begitu disimpan. Owner sengaja tak bisa diubah supaya tak ada
                keadaan semua orang terkunci di luar. ProdPilot itu tautan ke aplikasi lain —
                mencabutnya hanya menyembunyikan menunya di sini.
            </p>
        </div>
    </Layout>
</template>

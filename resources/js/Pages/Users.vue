<script setup>
// Halaman User: kelola akun & hak akses (port dari Users.jsx / users/index.blade.php)
import { ref } from 'vue';                              // ref untuk state lokal modal & mode
import { router, useForm } from '@inertiajs/vue3';      // useForm untuk form, router untuk delete
import Layout from '../Layout.vue';                     // layout bersama (sidebar + toast)

// Props dari controller: daftar user & peta role
const props = defineProps({                             // definisi props Inertia
    users: Array,                                       // array data user
    roles: Object,                                      // objek key->label role
});                                                     // akhir defineProps

// Warna badge per role — key WAJIB cocok dengan User::ROLES
const roleColors = {                                    // map role -> kelas badge
    owner: 'bg-rose-600 text-white',                    // owner merah
    manager: 'bg-brand-600 text-white',                 // manager brand
    it: 'bg-violet-600 text-white',                     // IT ungu
    admin: 'bg-sky-600 text-white',                     // admin biru (CRUD terbatas)
    staff: 'bg-slate-200 text-slate-700',               // staff abu (view-only)
};                                                      // akhir map warna

const open = ref(false);                                // status modal terbuka
const mode = ref('create');                             // mode form: create/edit
const editId = ref(null);                               // id user yang sedang diedit
const showPassword = ref(false);                        // tampilkan/sembunyikan teks password di form

// Form Inertia dengan field sesuai blade
const form = useForm({                                  // inisialisasi useForm
    name: '',                                           // field nama
    email: '',                                          // field email
    password: '',                                       // field password
    role: 'manager',                                    // default role manager
});                                                     // akhir useForm

// Buka modal untuk tambah user baru
const openCreate = () => {                              // handler tambah
    mode.value = 'create';                              // set mode create
    editId.value = null;                                // tidak ada id edit
    showPassword.value = false;                         // sembunyikan password lagi tiap buka form
    form.reset();                                       // kosongkan field
    form.clearErrors();                                 // bersihkan error lama
    open.value = true;                                  // tampilkan modal
};                                                      // akhir openCreate

// Buka modal untuk edit user tertentu
const openEdit = (u) => {                               // handler edit
    mode.value = 'edit';                                // set mode edit
    editId.value = u.id;                                // simpan id user
    showPassword.value = false;                         // sembunyikan password lagi tiap buka form
    form.clearErrors();                                 // bersihkan error lama
    form.name = u.name;                                 // isi nama dari user
    form.email = u.email;                               // isi email dari user
    form.password = '';                                 // password kosong (opsional)
    form.role = u.role;                                 // isi role dari user
    open.value = true;                                  // tampilkan modal
};                                                      // akhir openEdit

// Submit form: create -> POST, edit -> PUT
// reset() WAJIB di dalam onSuccess, bukan cuma di openCreate().
// Inertia v3 menjadikan data yang barusan dikirim sbg `defaults` baru setelah submit
// sukses, jadi form.reset() di openCreate() malah memunculkan user sebelumnya —
// termasuk password. Callback ini jalan SEBELUM Inertia menyimpan defaults barunya,
// jadi yang tertangkap = form kosong.
const submit = () => {                                  // handler submit
    const done = { onSuccess: () => { open.value = false; form.reset(); } };
    if (mode.value === 'create') {                      // jika mode create
        form.post('/users', done);                      // kirim POST
    } else {                                            // jika mode edit
        form.put('/users/' + editId.value, done);       // kirim PUT
    }                                                   // akhir cabang mode
};                                                      // akhir submit

// Reset password cepat: minta password baru lalu kirim ke endpoint update.
// Sengaja pakai router.put (bukan modal edit) supaya admin bisa reset tanpa
// membuka form penuh — field lain diambil apa adanya dari data user.
const resetPassword = (u) => {                          // handler reset password
    const pw = prompt('Password baru untuk "' + u.name + '":'); // minta password baru
    if (pw === null) return;                            // batal bila dialog ditutup
    if (pw.length < 6) { alert('Password minimal 6 karakter.'); return; } // validasi minimal
    router.put('/users/' + u.id, { name: u.name, email: u.email, role: u.role, password: pw },
        { preserveScroll: true });                      // kirim PUT, jaga posisi scroll
};                                                      // akhir resetPassword

// Hapus user dengan konfirmasi
const destroy = (u) => {                                // handler hapus
    if (confirm('Hapus user "' + u.name + '"? Tindakan ini tidak bisa dibatalkan.')) { // konfirmasi
        router.delete('/users/' + u.id);                // kirim DELETE
    }                                                   // akhir konfirmasi
};                                                      // akhir destroy
</script>

<template>
    <!-- Bungkus dalam layout bersama (sidebar + toast) -->
    <Layout title="User">
        <!-- Header gradient brand -->
        <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
            <div class="px-6 py-5 flex items-center justify-between"> <!-- baris header -->
                <div> <!-- blok judul -->
                    <h1 class="text-2xl font-bold tracking-tight">USER</h1> <!-- judul -->
                    <p class="text-brand-100 text-sm">Kelola akun & hak akses</p> <!-- subjudul -->
                </div> <!-- akhir blok judul -->
                <!-- tombol tambah user -->
                <button @click="openCreate"
                        class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah User <!-- teks tombol -->
                </button> <!-- akhir tombol -->
            </div> <!-- akhir baris header -->
        </header> <!-- akhir header -->

        <!-- Body halaman -->
        <div class="px-6 py-6"> <!-- padding konten -->
            <div class="bg-white rounded-2xl shadow-sm border border-brand-100 overflow-x-auto"> <!-- kartu tabel -->
                <table class="min-w-full text-sm"> <!-- tabel user -->
                    <thead> <!-- kepala tabel -->
                        <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide"> <!-- baris header -->
                            <th class="px-4 py-3 text-left">Nama / Username</th> <!-- kolom nama -->
                            <th class="px-4 py-3 text-left">Email</th> <!-- kolom email -->
                            <th class="px-4 py-3 text-left">Status</th> <!-- kolom status -->
                            <th class="px-4 py-3 text-center">Aksi</th> <!-- kolom aksi -->
                        </tr> <!-- akhir baris header -->
                    </thead> <!-- akhir kepala tabel -->
                    <tbody class="divide-y divide-brand-50"> <!-- isi tabel -->
                        <!-- baris kosong bila belum ada user -->
                        <tr v-if="users.length === 0"><td :colspan="4" class="px-4 py-10 text-center text-slate-400">Belum ada user.</td></tr>
                        <!-- loop tiap user bila ada data -->
                        <tr v-for="u in users" :key="u.id" class="hover:bg-brand-50/60 transition"> <!-- baris user -->
                            <td class="px-4 py-2.5 font-semibold text-slate-700">{{ u.name }}</td> <!-- nama -->
                            <td class="px-4 py-2.5 text-slate-500">{{ u.email }}</td> <!-- email -->
                            <td class="px-4 py-2.5"> <!-- sel status -->
                                <span :class="'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (roleColors[u.role] || 'bg-slate-200 text-slate-700')">
                                    {{ roles[u.role] || u.role }} <!-- label role -->
                                </span> <!-- akhir badge -->
                            </td> <!-- akhir sel status -->
                            <td class="px-4 py-2.5 text-center whitespace-nowrap"> <!-- sel aksi -->
                                <div class="flex items-center justify-center gap-1.5"> <!-- grup tombol -->
                                    <!-- tombol edit -->
                                    <button @click="openEdit(u)"
                                            class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">Edit</button>
                                    <!-- tombol reset password -->
                                    <button type="button" @click="resetPassword(u)"
                                            class="bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Reset Password</button>
                                    <!-- tombol hapus -->
                                    <button type="button" @click="destroy(u)"
                                            class="bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                                </div> <!-- akhir grup tombol -->
                            </td> <!-- akhir sel aksi -->
                        </tr> <!-- akhir baris user -->
                    </tbody> <!-- akhir isi tabel -->
                </table> <!-- akhir tabel -->
            </div> <!-- akhir kartu tabel -->
            <p class="text-xs text-slate-400 mt-3">{{ users.length }} user.</p> <!-- jumlah user -->
        </div> <!-- akhir body -->

        <!-- Modal Tambah/Edit: tampil hanya saat open true -->
        <div v-if="open" class="fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4"
             @click="open = false"> <!-- klik backdrop menutup modal -->
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border-t-4 border-brand-600"
                 @click.stop> <!-- cegah tutup saat klik dalam -->
                <div class="flex items-center justify-between mb-5"> <!-- header modal -->
                    <h2 class="text-lg font-bold text-brand-800">{{ mode === 'create' ? 'Tambah User' : 'Edit User' }}</h2> <!-- judul modal dinamis -->
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                </div> <!-- akhir header modal -->
                <!-- form modal, submit dicegah default lalu panggil submit() -->
                <form @submit.prevent="submit" class="space-y-3 text-sm">
                    <label class="block font-medium text-slate-600">Nama / Username <!-- field nama -->
                        <input name="name" v-model="form.name" required
                               class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</span> <!-- error nama -->
                    </label> <!-- akhir field nama -->
                    <label class="block font-medium text-slate-600">Email <!-- field email -->
                        <input type="email" name="email" v-model="form.email" required
                               class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span> <!-- error email -->
                    </label> <!-- akhir field email -->
                    <label class="block font-medium text-slate-600"> <!-- field password -->
                        <span>{{ mode === 'create' ? 'Password' : 'Password (kosongkan bila tidak diubah)' }}</span> <!-- teks label dinamis -->
                        <div class="relative mt-1"> <!-- bungkus input + tombol mata -->
                            <!-- type ikut showPassword: text = kelihatan, password = tersembunyi -->
                            <input :type="showPassword ? 'text' : 'password'" name="password" v-model="form.password"
                                   :required="mode === 'create'" autocomplete="new-password"
                                   class="w-full border border-slate-200 rounded-xl px-3 py-2 pr-11 focus:ring-2 focus:ring-brand-400 outline-none" />
                            <!-- tombol toggle tampil/sembunyi password -->
                            <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600 text-xs font-medium">
                                {{ showPassword ? 'Sembunyikan' : 'Lihat' }}
                            </button>
                        </div> <!-- akhir bungkus password -->
                        <span v-if="form.errors.password" class="text-xs text-red-600">{{ form.errors.password }}</span> <!-- error password -->
                    </label> <!-- akhir field password -->
                    <label class="block font-medium text-slate-600">Status <!-- field role -->
                        <select name="role" v-model="form.role"
                                class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                            <!-- loop opsi role dari prop roles -->
                            <option v-for="(label, key) in roles" :key="key" :value="key">{{ label }}</option>
                        </select> <!-- akhir select -->
                        <span v-if="form.errors.role" class="text-xs text-red-600">{{ form.errors.role }}</span> <!-- error role -->
                    </label> <!-- akhir field role -->
                    <div class="flex justify-end gap-2 pt-2"> <!-- tombol aksi form -->
                        <button type="button" @click="open = false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">Simpan</button>
                    </div> <!-- akhir tombol aksi -->
                </form> <!-- akhir form modal -->
            </div> <!-- akhir kotak modal -->
        </div> <!-- akhir modal -->
    </Layout>
</template>

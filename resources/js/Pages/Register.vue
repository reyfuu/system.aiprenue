<script setup>
// Halaman registrasi mandiri — tanpa sidebar/Layout (full-screen), kembar Login.vue.
import { ref } from 'vue';                  // ref untuk state lokal (toggle password)
import { Head, Link, useForm } from '@inertiajs/vue3'; // Head judul tab, Link nav SPA, useForm form

// Form Inertia versi Vue: field top-level (bukan form.data). Bind pakai v-model="form.xxx".
// password_confirmation namanya WAJIB persis begitu — aturan `confirmed` di
// server mencari field bernama itu; ganti namanya = validasi selalu gagal.
const form = useForm({
    name: '',                   // nama tampil
    email: '',                  // email, dipakai untuk login
    password: '',               // password baru
    password_confirmation: '',  // ulangi password
});

// Toggle lihat/sembunyi password (dipakai bersama untuk kedua field)
const show = ref(false);

// Submit → POST /register (CSRF otomatis). onFinish kosongkan kedua field password.
const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <!-- Latar gradient full-screen, form di tengah -->
    <div class="min-h-screen bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 flex items-center justify-center p-4">
        <!-- Judul tab -->
        <Head title="Daftar — System AI Preneur" />

        <div class="w-full max-w-md">
            <!-- Header brand -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-white tracking-tight">SYSTEM AI PRENEUR</h1>
                <p class="text-brand-100 text-sm mt-1">Buat akun baru</p>
            </div>

            <!-- Kartu form -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Field nama -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nama</label>
                        <input
                            type="text"
                            v-model="form.name"
                            required
                            autofocus
                            placeholder="Nama lengkap"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                        />
                        <!-- Error per-field dari server -->
                        <p v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</p>
                    </div>

                    <!-- Field email -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                        <input
                            type="email"
                            v-model="form.email"
                            required
                            placeholder="nama@contoh.com"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                        />
                        <!-- Termasuk pesan "email sudah dipakai" dari aturan unique -->
                        <p v-if="form.errors.email" class="text-red-600 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>

                    <!-- Field password + toggle lihat -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Password</label>
                        <div class="relative">
                            <input
                                :type="show ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                placeholder="Minimal 6 karakter"
                                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 pr-11 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                            />
                            <button
                                type="button"
                                @click="show = !show"
                                :tabindex="-1"
                                :title="show ? 'Sembunyikan password' : 'Lihat password'"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-brand-600"
                            >
                                <!-- Ikon mata terbuka: password tersembunyi -->
                                <svg v-if="!show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12z" /></svg>
                                <!-- Ikon mata coret: password terlihat -->
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M9.9 4.7A9.6 9.6 0 0112 4.5c6.5 0 9.5 6.5 9.5 6.5a15 15 0 01-3 3.9M6.1 6.1A15 15 0 002.5 11s3 6.5 9.5 6.5a9.3 9.3 0 003.4-.6" /></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-red-600 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>

                    <!-- Ulangi password — ikut toggle `show` yang sama supaya user
                         bisa mencocokkan keduanya sekaligus, bukan satu-satu. -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Ulangi password</label>
                        <input
                            :type="show ? 'text' : 'password'"
                            v-model="form.password_confirmation"
                            required
                            placeholder="Ketik ulang password"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                        />
                    </div>

                    <!-- Tombol submit (disable saat proses) -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-xl transition shadow disabled:opacity-60"
                    >
                        {{ form.processing ? 'Memproses…' : 'Daftar' }}
                    </button>
                </form>

                <!-- Jalan balik untuk yang ternyata sudah punya akun -->
                <p class="text-center text-sm text-slate-500 mt-5">
                    Sudah punya akun?
                    <Link href="/login" class="font-medium text-brand-600 hover:text-brand-800">Masuk</Link>
                </p>
            </div>

            <!-- Footer -->
            <p class="text-center text-brand-100 text-xs mt-6">&copy; {{ new Date().getFullYear() }} AI Preneur</p>
        </div>
    </div>
</template>

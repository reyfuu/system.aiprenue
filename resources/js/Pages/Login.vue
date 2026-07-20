<script setup>
// Halaman login — tanpa sidebar/Layout (full-screen). Port dari resources/views/auth/login.blade.php.
import { ref } from 'vue';                       // ref untuk state lokal (toggle password)
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'; // Head judul tab, Link nav SPA, useForm form, usePage flash

// Form Inertia versi Vue: field top-level (bukan form.data). Bind pakai v-model="form.xxx".
const form = useForm({
    email: '',        // input email
    password: '',     // input password
    remember: false,  // checkbox "ingat saya"
});

// Toggle lihat/sembunyi password
const show = ref(false);

// Flash dari server — dipakai pesan "Password berhasil dibuat. Silakan masuk."
// sesudah user selesai mengatur passwordnya lewat tautan reset.
const page = usePage();

// Submit → POST /login (CSRF otomatis). onFinish kosongkan password setelah kirim.
const submit = () => {
    form.post('/login', { onFinish: () => form.reset('password') });
};
</script>

<template>
    <!-- Latar gradient full-screen, form di tengah -->
    <div class="min-h-screen bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 flex items-center justify-center p-4">
        <!-- Judul tab -->
        <Head title="Masuk — System AI Preneur" />

        <div class="w-full max-w-md">
            <!-- Header brand -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-white tracking-tight">SYSTEM AI PRENEUR</h1>
                <p class="text-brand-100 text-sm mt-1">Masuk untuk mengelola pipeline</p>
            </div>

            <!-- Kartu form -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <!-- Pesan sukses (mis. sesudah password dibuat lewat tautan reset) -->
                <div v-if="page.props.flash?.status" class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-2.5 rounded-xl">
                    {{ page.props.flash.status }}
                </div>

                <!-- Pesan error (email/password salah) -->
                <div v-if="form.errors.email" class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2.5 rounded-xl">
                    {{ form.errors.email }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Field email -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                        <input
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            placeholder="admin@example.com"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                        />
                    </div>

                    <!-- Field password + toggle lihat -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Password</label>
                        <div class="relative">
                            <input
                                :type="show ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                placeholder="••••••"
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
                    </div>

                    <!-- Ingat saya + jalan keluar kalau lupa password.
                         Ditaruh sebaris & tepat di bawah field password: di situlah
                         orang menyadari dirinya lupa, bukan di footer halaman. -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                v-model="form.remember"
                                class="accent-brand-600"
                            /> Ingat saya
                        </label>
                        <Link href="/forgot-password" class="text-sm font-medium text-brand-600 hover:text-brand-800">
                            Lupa password?
                        </Link>
                    </div>

                    <!-- Tombol submit (disable saat proses) -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-xl transition shadow disabled:opacity-60"
                    >
                        {{ form.processing ? 'Memproses…' : 'Masuk' }}
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <p class="text-center text-brand-100 text-xs mt-6">&copy; {{ new Date().getFullYear() }} AI Preneur</p>
        </div>
    </div>
</template>

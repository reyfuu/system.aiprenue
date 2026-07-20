<script setup>
// Halaman pasang password baru — dibuka dari tautan di email.
// Token & email datang dari URL (dikirim controller sbg props), bukan diketik user.
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: String,          // token reset dari tautan email
    email: String,          // email pemilik tautan (query string)
});

// Field top-level sesuai konvensi useForm repo ini. `password_confirmation`
// namanya WAJIB persis begitu — aturan `confirmed` di server mencarinya.
const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
});

const show = ref(false);

// onFinish kosongkan kedua field password: kalau submit gagal (token kedaluwarsa),
// password jangan tertinggal di form.
const submit = () => form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 flex items-center justify-center p-4">
        <Head title="Buat Password — System AI Preneur" />

        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-white tracking-tight">SYSTEM AI PRENEUR</h1>
                <p class="text-brand-100 text-sm mt-1">Buat password barumu</p>
            </div>

            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <!-- Error: token kedaluwarsa / email tak cocok -->
                <div v-if="form.errors.email" class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2.5 rounded-xl">
                    {{ form.errors.email }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email dikunci: nilainya dari tautan, bukan diketik.
                         Kalau bisa diubah, orang bisa memakai tautannya sendiri
                         untuk mereset akun orang lain. -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                        <input
                            type="email"
                            :value="form.email"
                            readonly
                            class="w-full border border-slate-200 bg-slate-50 text-slate-500 rounded-xl px-4 py-2.5 outline-none"
                        />
                    </div>

                    <!-- Password baru + toggle lihat -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Password baru</label>
                        <div class="relative">
                            <input
                                :type="show ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                autofocus
                                placeholder="minimal 8 karakter"
                                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 pr-11 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                            />
                            <button
                                type="button"
                                @click="show = !show"
                                :tabindex="-1"
                                :title="show ? 'Sembunyikan password' : 'Lihat password'"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-brand-600"
                            >
                                <svg v-if="!show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12z" /></svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M9.9 4.7A9.6 9.6 0 0112 4.5c6.5 0 9.5 6.5 9.5 6.5a15 15 0 01-3 3.9M6.1 6.1A15 15 0 002.5 11s3 6.5 9.5 6.5a9.3 9.3 0 003.4-.6" /></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-red-600 text-xs mt-1">{{ form.errors.password }}</p>
                    </div>

                    <!-- Ulangi password: salah ketik password baru tanpa ini = terkunci di luar -->
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Ulangi password</label>
                        <input
                            :type="show ? 'text' : 'password'"
                            v-model="form.password_confirmation"
                            required
                            placeholder="ketik ulang password"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-xl transition disabled:opacity-60"
                    >
                        {{ form.processing ? 'Menyimpan…' : 'Simpan password' }}
                    </button>
                </form>

                <p class="text-center text-sm text-slate-500 mt-5">
                    <Link href="/login" class="text-brand-600 hover:text-brand-800 font-medium">Kembali ke halaman masuk</Link>
                </p>
            </div>
        </div>
    </div>
</template>

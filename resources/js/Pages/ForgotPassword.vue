<script setup>
// Halaman "lupa password": isi email, sistem kirim tautan untuk memasang password sendiri.
// Tanpa sidebar/Layout (full-screen), seragam dgn Login.vue.
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

// Field top-level (bukan form.data) sesuai konvensi useForm repo ini.
const form = useForm({ email: '' });

// Pesan sukses dari server (flash). Sengaja SATU pesan yang sama entah emailnya
// terdaftar atau tidak — biar halaman ini tak bisa dipakai menebak email siapa
// yang punya akun.
const page = usePage();

const submit = () => form.post('/forgot-password');
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 flex items-center justify-center p-4">
        <Head title="Lupa Password — System AI Preneur" />

        <div class="w-full max-w-md">
            <!-- Header brand -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-white tracking-tight">SYSTEM AI PRENEUR</h1>
                <p class="text-brand-100 text-sm mt-1">Atur password akunmu</p>
            </div>

            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <!-- Pesan terkirim -->
                <div v-if="page.props.flash?.status" class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 rounded-xl">
                    {{ page.props.flash.status }}
                </div>

                <!-- Error validasi (format email salah / terlalu sering minta) -->
                <div v-if="form.errors.email" class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2.5 rounded-xl">
                    {{ form.errors.email }}
                </div>

                <p class="text-sm text-slate-600 mb-4">
                    Masukkan email yang didaftarkan admin. Kami kirim tautan untuk membuat
                    passwordmu sendiri. Tautannya berlaku 60 menit.
                </p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                        <input
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            placeholder="nama@email.com"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-xl transition disabled:opacity-60"
                    >
                        {{ form.processing ? 'Mengirim…' : 'Kirim tautan' }}
                    </button>
                </form>

                <!-- Kembali ke login -->
                <p class="text-center text-sm text-slate-500 mt-5">
                    Sudah ingat passwordmu?
                    <Link href="/login" class="text-brand-600 hover:text-brand-800 font-medium">Masuk</Link>
                </p>
            </div>
        </div>
    </div>
</template>

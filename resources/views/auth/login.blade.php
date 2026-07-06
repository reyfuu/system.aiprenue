<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Pipeline FK-AI Preneur</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-white tracking-tight">PIPELINE FK-AI PRENEUR</h1>
            <p class="text-brand-100 text-sm mt-1">Masuk untuk mengelola pipeline</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-8">
            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2.5 rounded-xl">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                           placeholder="admin@example.com">
                </div>
                <div x-data="{ show: false }">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Password</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="password" required
                               class="w-full border border-slate-200 rounded-xl px-4 py-2.5 pr-11 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                               placeholder="••••••">
                        <button type="button" @click="show = !show" tabindex="-1"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-brand-600"
                                :title="show ? 'Sembunyikan password' : 'Lihat password'">
                            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12z"/></svg>
                            <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M9.9 4.7A9.6 9.6 0 0112 4.5c6.5 0 9.5 6.5 9.5 6.5a15 15 0 01-3 3.9M6.1 6.1A15 15 0 002.5 11s3 6.5 9.5 6.5a9.3 9.3 0 003.4-.6"/></svg>
                        </button>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="accent-brand-600"> Ingat saya
                </label>
                <button type="submit"
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-xl transition shadow">
                    Masuk
                </button>
            </form>
        </div>

        <p class="text-center text-brand-100 text-xs mt-6">&copy; {{ date('Y') }} FK-AI Preneur</p>
    </div>
</body>
</html>

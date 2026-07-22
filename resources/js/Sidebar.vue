<script setup>
// Sidebar navigasi. Menu digating oleh auth.user.menus (dibagikan HandleInertiaRequests).
import { computed, ref } from 'vue';                     // computed turunan reaktif, ref state modal
import { Link, usePage, router, useForm } from '@inertiajs/vue3'; // Link nav, usePage props, router aksi, useForm form

// Daftar menu: key cocok dgn auth.user.menus, href tujuan, path ikon SVG.
const ITEMS = [
    { key: 'dashboard', label: 'Dashboard', href: '/dashboard',        icon: 'M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 13h6v6h-6z' },
    { key: 'pipeline',  label: 'Sales',     href: '/pipelines',        icon: 'M3 10h18M3 6h18M3 14h18M3 18h18' },
    { key: 'order',     label: 'Order',     href: '/orders',           icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
    { key: 'kanban',    label: 'Kanban',    href: '/pipelines/kanban', icon: 'M4 5h4v14H4zM10 5h4v9h-4zM16 5h4v6h-4z' },
    { key: 'insight',   label: 'Insight',   href: '/insight',          icon: 'M3 3v18h18M7 15l3-4 3 3 5-7' },
    { key: 'upload',    label: 'Upload',    href: '/upload',           icon: 'M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4' },
    { key: 'content',   label: 'Content',   href: '/content',          icon: 'M4 5h16v14H4zM8 9h8M8 13h5' },
    { key: 'tracking',  label: 'Tracking',  href: '/tracking',         icon: 'M4 19V9m5 10V5m5 14v-7m5 7V3' },
    { key: 'mindmap',   label: 'Mindmap',   href: '/mindmaps',         icon: 'M4 6a2 2 0 114 0 2 2 0 01-4 0zm12-2a2 2 0 100 4 2 2 0 000-4zm0 12a2 2 0 100 4 2 2 0 000-4zM8 6h4a2 2 0 012 2v0m0 8a2 2 0 00-2-2H8m0-8v8' },
    { key: 'script',    label: 'Script',    href: '/script',           icon: 'M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z' },
    { key: 'pembukuan', label: 'Pembukuan', href: '/pembukuan',        icon: 'M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 011-1z' },
    { key: 'user',      label: 'User',      href: '/users',            icon: 'M17 20h5v-1a4 4 0 00-4-4h-1m-6 5H2v-1a4 4 0 014-4h4a4 4 0 014 4v1zm-2-9a4 4 0 11-8 0 4 4 0 018 0zm7 0a3 3 0 11-6 0 3 3 0 016 0z' },
    { key: 'akses',     label: 'Manajemen Akses', href: '/akses',    icon: 'M12 11c0-1.1.9-2 2-2s2 .9 2 2m-4 0v0M5 11h14a1 1 0 011 1v8a1 1 0 01-1 1H5a1 1 0 01-1-1v-8a1 1 0 011-1zm3 0V7a4 4 0 018 0v4' },
    // Tautan EKSTERNAL ke aplikasi ProdPilot — buka tab baru, bukan navigasi SPA.
    // `external: true` cuma soal RENDER (<a> bukan <Link>). Hak aksesnya tetap dari
    // menus.prodpilot (User::MENU_ACCESS) — owner/it/manager saja.
    { key: 'prodpilot', label: 'ProdPilot', href: 'https://prodpilot.aipreneur.co.id', external: true, icon: 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14' },
];

const page = usePage();                                     // akses shared props (auth, url)
const user = computed(() => page.props.auth?.user);         // user login (atau undefined)

// URL cocok bila sama persis / diawali href + '/' atau '?'
const matchHref = (href) => page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
// href TERPANJANG yg cocok = menu aktif (agar /pipelines/kanban tak menyorot /pipelines)
const activeHref = computed(() => ITEMS.filter((it) => matchHref(it.href)).reduce((best, it) => (it.href.length > best.length ? it.href : best), ''));
// Menu yg boleh dilihat user ini — SEMUA item digating `menus[key]` dari backend,
// termasuk yang eksternal. `external` cuma menentukan cara render (<a> vs <Link>),
// BUKAN hak akses; dulu dipakai utk melewati gating & itu bikin ProdPilot tampil
// ke semua peran.
const visibleItems = computed(() => ITEMS.filter((it) => user.value?.menus[it.key]));

const logout = () => router.post('/logout');                // POST /logout (CSRF otomatis)

// Ganti password sendiri (self-service). Modal kecil dari footer sidebar —
// verifikasi pakai password lama, tanpa email/SMTP.
const pwOpen = ref(false);                                  // status modal ganti password
const showPw = ref(false);                                  // toggle lihat/sembunyi isian
const pwForm = useForm({                                    // form 3 field
    current_password: '',                                  // password lama (diverifikasi server)
    password: '',                                          // password baru
    password_confirmation: '',                             // konfirmasi password baru
});

// Buka modal: reset field & error dulu supaya bersih tiap dibuka
const openPw = () => {                                      // handler buka modal
    showPw.value = false;                                  // mulai tersembunyi
    pwForm.reset();                                         // kosongkan field
    pwForm.clearErrors();                                   // bersihkan error lama
    pwOpen.value = true;                                   // tampilkan modal
};                                                          // akhir openPw

// Submit: PUT /profile/password; tutup & kosongkan bila sukses
const submitPw = () => {                                    // handler submit
    pwForm.put('/profile/password', {                      // kirim PUT
        preserveScroll: true,                              // jangan lompat scroll
        onSuccess: () => { pwOpen.value = false; pwForm.reset(); }, // sukses → tutup & reset
    });                                                    // akhir put
};                                                          // akhir submitPw
</script>

<template>
    <!-- Render hanya bila ada user login -->
    <aside v-if="user" class="hidden md:flex flex-col fixed left-0 top-0 h-screen w-56 bg-brand-800 text-brand-100 z-40">
        <!-- Header brand -->
        <div class="px-5 py-5 border-b border-white/10">
            <p class="text-white font-bold leading-tight">SYSTEM AI PRENEUR</p>
            <p class="text-[11px] text-brand-200">Pipeline endorsement</p>
        </div>
        <!-- Navigasi -->
        <nav class="flex-1 p-3 space-y-1 text-sm">
            <!-- <a> utk tautan eksternal (tab baru), <Link> Inertia utk menu internal.
                 target/rel cuma diisi saat eksternal supaya menu internal tetap navigasi SPA. -->
            <component
                :is="it.external ? 'a' : Link"
                v-for="it in visibleItems"
                :key="it.key"
                :href="it.href"
                :target="it.external ? '_blank' : undefined"
                :rel="it.external ? 'noreferrer' : undefined"
                :class="['flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition', it.href === activeHref ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10']"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="it.icon" />
                </svg>
                {{ it.label }}
            </component>
        </nav>
        <!-- Footer: nama user + ganti password + tombol logout -->
        <div class="p-3 border-t border-white/10 flex items-center justify-between">
            <span class="text-[11px] text-brand-200 truncate">{{ user.name }}</span>
            <div class="flex items-center gap-2"> <!-- grup aksi akun -->
                <!-- tombol ganti password sendiri (ikon kunci) -->
                <button type="button" @click="openPw" class="text-brand-200 hover:text-white" title="Ganti password">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                </button>
                <!-- tombol logout -->
                <form @submit.prevent="logout">
                    <button class="text-brand-200 hover:text-white" title="Keluar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    </button>
                </form>
            </div> <!-- akhir grup aksi -->
        </div>
    </aside>

    <!-- Modal ganti password sendiri: tampil hanya saat pwOpen true -->
    <div v-if="pwOpen" class="fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4"
         @click="pwOpen = false"> <!-- klik backdrop menutup -->
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 border-t-4 border-brand-600 text-slate-700"
             @click.stop> <!-- cegah tutup saat klik dalam -->
            <div class="flex items-center justify-between mb-5"> <!-- header modal -->
                <h2 class="text-lg font-bold text-brand-800">Ganti Password</h2>
                <button type="button" @click="pwOpen = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>
            <!-- form ganti password: cegah default lalu submitPw() -->
            <form @submit.prevent="submitPw" class="space-y-3 text-sm">
                <!-- password lama (diverifikasi server via rule current_password) -->
                <label class="block font-medium text-slate-600">Password Lama
                    <input :type="showPw ? 'text' : 'password'" v-model="pwForm.current_password" required autocomplete="current-password"
                           class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="pwForm.errors.current_password" class="text-xs text-red-600">{{ pwForm.errors.current_password }}</span>
                </label>
                <!-- password baru -->
                <label class="block font-medium text-slate-600">Password Baru
                    <input :type="showPw ? 'text' : 'password'" v-model="pwForm.password" required autocomplete="new-password"
                           class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                    <span v-if="pwForm.errors.password" class="text-xs text-red-600">{{ pwForm.errors.password }}</span>
                </label>
                <!-- konfirmasi password baru -->
                <label class="block font-medium text-slate-600">Ulangi Password Baru
                    <input :type="showPw ? 'text' : 'password'" v-model="pwForm.password_confirmation" required autocomplete="new-password"
                           class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                </label>
                <!-- toggle lihat/sembunyi semua isian -->
                <label class="flex items-center gap-2 text-xs text-slate-500">
                    <input type="checkbox" v-model="showPw" class="accent-brand-600" /> Lihat password
                </label>
                <div class="flex justify-end gap-2 pt-2"> <!-- tombol aksi -->
                    <button type="button" @click="pwOpen = false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="pwForm.processing" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
// Sidebar navigasi. Menu digating oleh auth.user.menus (dibagikan HandleInertiaRequests).
import { computed } from 'vue';                          // computed untuk turunan reaktif
import { Link, usePage, router } from '@inertiajs/vue3'; // Link nav, usePage props, router aksi

// Daftar menu: key cocok dgn auth.user.menus, href tujuan, path ikon SVG.
const ITEMS = [
    { key: 'dashboard', label: 'Dashboard', href: '/dashboard',        icon: 'M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 13h6v6h-6z' },
    { key: 'pipeline',  label: 'Sales',     href: '/pipelines',        icon: 'M3 10h18M3 6h18M3 14h18M3 18h18' },
    { key: 'order',     label: 'Order',     href: '/orders',           icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
    { key: 'kanban',    label: 'Kanban',    href: '/pipelines/kanban', icon: 'M4 5h4v14H4zM10 5h4v9h-4zM16 5h4v6h-4z' },
    { key: 'mindmap',   label: 'Mindmap',   href: '/mindmaps',         icon: 'M4 6a2 2 0 114 0 2 2 0 01-4 0zm12-2a2 2 0 100 4 2 2 0 000-4zm0 12a2 2 0 100 4 2 2 0 000-4zM8 6h4a2 2 0 012 2v0m0 8a2 2 0 00-2-2H8m0-8v8' },
    { key: 'script',    label: 'Script',    href: '/script',           icon: 'M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z' },
    { key: 'pembukuan', label: 'Pembukuan', href: '/pembukuan',        icon: 'M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 011-1z' },
    { key: 'user',      label: 'User',      href: '/users',            icon: 'M17 20h5v-1a4 4 0 00-4-4h-1m-6 5H2v-1a4 4 0 014-4h4a4 4 0 014 4v1zm-2-9a4 4 0 11-8 0 4 4 0 018 0zm7 0a3 3 0 11-6 0 3 3 0 016 0z' },
];

const page = usePage();                                     // akses shared props (auth, url)
const user = computed(() => page.props.auth?.user);         // user login (atau undefined)

// URL cocok bila sama persis / diawali href + '/' atau '?'
const matchHref = (href) => page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
// href TERPANJANG yg cocok = menu aktif (agar /pipelines/kanban tak menyorot /pipelines)
const activeHref = computed(() => ITEMS.filter((it) => matchHref(it.href)).reduce((best, it) => (it.href.length > best.length ? it.href : best), ''));
// Menu yg boleh dilihat user ini
const visibleItems = computed(() => ITEMS.filter((it) => user.value?.menus[it.key]));

const logout = () => router.post('/logout');                // POST /logout (CSRF otomatis)
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
            <Link
                v-for="it in visibleItems"
                :key="it.key"
                :href="it.href"
                :class="['flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition', it.href === activeHref ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10']"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="it.icon" />
                </svg>
                {{ it.label }}
            </Link>
        </nav>
        <!-- Footer: nama user + tombol logout -->
        <div class="p-3 border-t border-white/10 flex items-center justify-between">
            <span class="text-[11px] text-brand-200 truncate">{{ user.name }}</span>
            <form @submit.prevent="logout">
                <button class="text-brand-200 hover:text-white" title="Keluar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                </button>
            </form>
        </div>
    </aside>
</template>

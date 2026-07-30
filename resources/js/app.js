// Entry Inertia + Vue 3. Menggantikan app.jsx (React) sebagai bundel utama.
import '../css/app.css';                              // muat Tailwind agar ikut ter-bundle
import { createInertiaApp } from '@inertiajs/vue3';   // bootstrap SPA Inertia (adapter Vue)
import { createApp, h } from 'vue';                   // Vue 3 create + render function

createInertiaApp({
    // resolve: ubah nama halaman (dari controller) → komponen .vue di ./Pages
    resolve: (name) => {
        // Glob semua page component sekali saat build (eager = sinkron)
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        // Kembalikan modul yg cocok, mis. name "Dashboard" → ./Pages/Dashboard.vue
        return pages[`./Pages/${name}.vue`];
    },
    // setup: buat instance Vue, pasang plugin Inertia, mount ke elemen root
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }) // render komponen halaman aktif
            .use(plugin)                           // daftarkan plugin Inertia (Link, router, dll)
            .mount(el);                            // tempel ke @inertia di app.blade.php
    },
    // Warna bar loading bawaan Inertia (nuansa brand)
    progress: { color: '#4f46e5' },
});

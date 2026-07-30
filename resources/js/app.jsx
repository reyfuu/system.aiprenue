// Entry Inertia + React. Menggantikan app.js (Alpine) sebagai bundel utama.
import '../css/app.css';                              // muat Tailwind agar ikut ter-bundle
import { createInertiaApp } from '@inertiajs/react';  // bootstrap SPA Inertia
import { createRoot } from 'react-dom/client';        // React 19 root API

createInertiaApp({
    // resolve: ubah nama halaman (dari controller) → komponen di ./Pages
    resolve: (name) => {
        // Glob semua page component sekali di build (eager = sinkron)
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        // Kembalikan modul yang cocok, mis. name "Dashboard" → ./Pages/Dashboard.jsx
        return pages[`./Pages/${name}.jsx`];
    },
    // setup: pasang komponen App ke elemen root (@inertia di app.blade.php)
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />); // render SPA
    },
    // Warna bar progres loading bawaan Inertia (nuansa brand)
    progress: { color: '#4f46e5' },
});

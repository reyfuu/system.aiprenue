// Layout utama: sidebar + area konten + toast flash. Dipakai semua halaman ber-sidebar.
import { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import Sidebar from './Sidebar';

export default function Layout({ title, children }) {
    const { flash } = usePage().props;      // flash.status dari redirect()->with('status')
    const [toast, setToast] = useState(null); // pesan toast aktif

    // Munculkan toast tiap ada flash baru, hilang otomatis 3 detik
    useEffect(() => {
        if (flash?.status) {
            setToast(flash.status);                       // set pesan
            const t = setTimeout(() => setToast(null), 3000); // auto-hide
            return () => clearTimeout(t);                 // bersihkan timer
        }
    }, [flash?.status]);

    return (
        <>
            {/* Judul tab browser per halaman */}
            {title && <Head title={`${title} — System AI Preneur`} />}

            {/* Sidebar tetap di kiri */}
            <Sidebar />

            {/* Konten digeser 56 (lebar sidebar) di layar md+ */}
            <div className="md:ml-56">{children}</div>

            {/* Toast flash message di kanan bawah */}
            {toast && (
                <div className="fixed bottom-5 right-5 z-50 bg-brand-700 text-white text-sm px-4 py-3 rounded-xl shadow-lg">
                    {toast}
                </div>
            )}
        </>
    );
}

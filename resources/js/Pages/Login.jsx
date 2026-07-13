// Halaman login — tanpa sidebar/Layout. Port dari resources/views/auth/login.blade.php.
import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    // Form Inertia: field email, password, remember
    const { data, setData, post, processing, errors } = useForm({
        email: '',      // input email
        password: '',   // input password
        remember: false, // checkbox "ingat saya"
    });
    const [show, setShow] = useState(false); // toggle lihat password

    // Submit → POST /login (CSRF otomatis)
    const submit = (e) => {
        e.preventDefault();                          // cegah reload
        post('/login', { onFinish: () => setData('password', '') }); // kosongkan password setelah kirim
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 flex items-center justify-center p-4">
            {/* Judul tab */}
            <Head title="Masuk — System AI Preneur" />

            <div className="w-full max-w-md">
                {/* Header brand */}
                <div className="text-center mb-6">
                    <h1 className="text-2xl font-bold text-white tracking-tight">SYSTEM AI PRENEUR</h1>
                    <p className="text-brand-100 text-sm mt-1">Masuk untuk mengelola pipeline</p>
                </div>

                {/* Kartu form */}
                <div className="bg-white rounded-2xl shadow-2xl p-8">
                    {/* Pesan error (email/password salah) */}
                    {errors.email && (
                        <div className="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2.5 rounded-xl">
                            {errors.email}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        {/* Field email */}
                        <div>
                            <label className="block text-sm font-medium text-slate-600 mb-1">Email</label>
                            <input
                                type="email"                                    // tipe email
                                value={data.email}                              // controlled value
                                onChange={(e) => setData('email', e.target.value)} // update state
                                required                                         // wajib
                                autoFocus                                        // fokus otomatis
                                placeholder="admin@example.com"                  // contoh
                                className="w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                            />
                        </div>

                        {/* Field password + toggle lihat */}
                        <div>
                            <label className="block text-sm font-medium text-slate-600 mb-1">Password</label>
                            <div className="relative">
                                <input
                                    type={show ? 'text' : 'password'}            // tampil/sembunyi
                                    value={data.password}                        // controlled
                                    onChange={(e) => setData('password', e.target.value)} // update
                                    required                                     // wajib
                                    placeholder="••••••"                         // placeholder
                                    className="w-full border border-slate-200 rounded-xl px-4 py-2.5 pr-11 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none"
                                />
                                <button
                                    type="button"                               // bukan submit
                                    onClick={() => setShow(!show)}              // toggle show
                                    tabIndex={-1}                               // skip dari tab
                                    title={show ? 'Sembunyikan password' : 'Lihat password'}
                                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-brand-600"
                                >
                                    {/* Ikon mata: buka bila tersembunyi, coret bila terlihat */}
                                    {!show ? (
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12z" /></svg>
                                    ) : (
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M9.9 4.7A9.6 9.6 0 0112 4.5c6.5 0 9.5 6.5 9.5 6.5a15 15 0 01-3 3.9M6.1 6.1A15 15 0 002.5 11s3 6.5 9.5 6.5a9.3 9.3 0 003.4-.6" /></svg>
                                    )}
                                </button>
                            </div>
                        </div>

                        {/* Checkbox ingat saya */}
                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"                                 // checkbox
                                checked={data.remember}                         // controlled
                                onChange={(e) => setData('remember', e.target.checked)} // update
                                className="accent-brand-600"
                            /> Ingat saya
                        </label>

                        {/* Tombol submit */}
                        <button
                            type="submit"
                            disabled={processing}                               // disable saat proses
                            className="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-xl transition shadow disabled:opacity-60"
                        >
                            {processing ? 'Memproses…' : 'Masuk'}
                        </button>
                    </form>
                </div>

                {/* Footer */}
                <p className="text-center text-brand-100 text-xs mt-6">&copy; {new Date().getFullYear()} AI Preneur</p>
            </div>
        </div>
    );
}

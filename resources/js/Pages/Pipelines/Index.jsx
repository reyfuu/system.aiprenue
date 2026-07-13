// Halaman Pipeline: tabel entri endorsement + filter + ringkasan + tab kategori + modal tambah/edit.
import { useState } from 'react';                                  // state lokal untuk modal & filter
import { router, useForm, Link, usePage } from '@inertiajs/react';  // helper navigasi & form Inertia
import Layout from '../../Layout';                                  // layout bersama (sidebar + toast)

// Helper format Rupiah: "Rp 1.234.567" (id-ID)
const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

// Warna badge per account (samakan dengan Pipeline::ACCOUNT_COLORS)
const ACCOUNT_COLORS = {
    fk: 'bg-brand-600 text-white',            // FK → brand
    ai_preneur: 'bg-violet-600 text-white',   // AI Preneur → violet
};

// Warna badge per progress (samakan dengan blade $pc)
const PROGRESS_COLORS = {
    script: 'bg-purple-600 text-white',       // script → ungu
    editing: 'bg-brand-100 text-brand-700',   // editing → brand muda
    progress: 'bg-brand-600 text-white',      // progress → brand
    pending: 'bg-amber-400 text-amber-900',   // pending → amber
    done: 'bg-emerald-600 text-white',        // done → hijau
};

// Warna badge per payment_status (samakan dengan blade $yc)
const PAYMENT_COLORS = {
    lunas: 'bg-emerald-600 text-white',       // lunas → hijau
    dp: 'bg-amber-400 text-amber-900',        // dp → amber
    belum: 'bg-red-600 text-white',           // belum → merah
};

// Warna badge per ke_gilang (samakan dengan blade $gc)
const GILANG_COLORS = {
    done: 'bg-emerald-600 text-white',        // done → hijau
    sudah: 'bg-brand-100 text-brand-700',     // sudah → brand muda
    belum: 'bg-red-600 text-white',           // belum → merah
};

// Format tanggal "13 Jul 2026" dari string ISO, atau em-dash bila kosong
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

// Komponen halaman (default export). Props diterima langsung dari controller.
export default function Index({ pipelines, category, counts, categories, outputs, summary, filters, accounts, progresses, payments, keGilang, staff }) {
    const { auth } = usePage().props;                                   // ambil user login dari shared props

    // State filter lokal (dipakai untuk kontrol input sebelum navigasi)
    const [f, setF] = useState({
        account: filters.account || '',            // filter account terpilih
        progress: filters.progress || '',          // filter progress terpilih
        payment_status: filters.payment_status || '', // filter payment terpilih
        output: filters.output || '',              // filter output terpilih
        search: filters.search || '',              // kata kunci pencarian
    });

    // State modal tambah/edit
    const [open, setOpen] = useState(false);       // modal terbuka?
    const [mode, setMode] = useState('create');    // 'create' | 'edit'
    const [editId, setEditId] = useState(null);    // id entri yang diedit

    // Form Inertia untuk modal (nilai awal = blank)
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        category: category,          // kategori aktif sebagai default
        account: 'fk',               // default account FK
        endorse: '',                 // nama endorse/produk
        outputs: [],                 // id output terpilih (array)
        progress: 'script',          // default progress script
        payment_status: 'belum',     // default payment belum
        tanggal_posting: '',         // tanggal posting
        tanggal_payment: '',         // tanggal payment
        amount_idr: '',              // nominal IDR
        amount_usd: '',              // nominal USD
        notes: '',                   // catatan panjang
        ke_gilang: 'belum',          // status ke gilang
        catatan: '',                 // catatan singkat
    });

    // Terapkan filter → navigasi GET Inertia (pertahankan state & scroll)
    const applyFilters = (next) => {
        const merged = { ...f, ...next };                                  // gabung nilai lama + perubahan
        setF(merged);                                                       // simpan ke state lokal
        router.get('/pipelines', { category, ...merged }, { preserveState: true, preserveScroll: true, replace: true }); // kirim ke server
    };

    // Buka modal tambah: reset form ke blank
    const openCreate = () => {
        clearErrors();                               // bersihkan error lama
        reset();                                     // kembalikan ke nilai awal useForm
        setData('category', category);               // set kategori aktif
        setMode('create');                           // mode create
        setEditId(null);                             // tak ada id
        setOpen(true);                               // tampilkan modal
    };

    // Buka modal edit: isi form dari data entri
    const openEdit = (p) => {
        clearErrors();                               // bersihkan error lama
        setData({
            category: p.category,                    // kategori entri
            account: p.account,                      // account entri
            endorse: p.endorse,                      // endorse entri
            outputs: (p.outputs || []).map((o) => o.id), // id output relasi
            progress: p.progress,                    // progress entri
            payment_status: p.payment_status,        // payment entri
            tanggal_posting: p.tanggal_posting ? p.tanggal_posting.substring(0, 10) : '', // ambil YYYY-MM-DD
            tanggal_payment: p.tanggal_payment ? p.tanggal_payment.substring(0, 10) : '', // ambil YYYY-MM-DD
            amount_idr: p.amount_idr ?? '',          // nominal IDR
            amount_usd: p.amount_usd ?? '',          // nominal USD
            notes: p.notes ?? '',                    // notes
            ke_gilang: p.ke_gilang,                  // status ke gilang
            catatan: p.catatan ?? '',                // catatan
        });
        setMode('edit');                             // mode edit
        setEditId(p.id);                             // simpan id
        setOpen(true);                               // tampilkan modal
    };

    // Toggle checkbox output pada form
    const toggleOutput = (id) => {
        const has = data.outputs.includes(id);                                // sudah tercentang?
        setData('outputs', has ? data.outputs.filter((x) => x !== id) : [...data.outputs, id]); // hapus/tambah id
    };

    // Submit form (create → post, edit → put), tutup modal bila sukses
    const submit = (e) => {
        e.preventDefault();                                        // cegah reload default
        const done = { onSuccess: () => setOpen(false) };          // tutup modal setelah berhasil
        if (mode === 'create') post('/pipelines', done);           // buat entri baru
        else put('/pipelines/' + editId, done);                    // perbarui entri
    };

    // Hapus entri dengan konfirmasi native
    const destroy = (p) => {
        if (confirm('Yakin ingin menghapus "' + p.endorse + '"? Tindakan ini tidak bisa dibatalkan.')) { // konfirmasi
            router.delete('/pipelines/' + p.id);                   // kirim DELETE
        }
    };

    // Nilai kurs untuk estimasi konversi di modal
    const rate = summary.rate;

    return (
        // Bungkus dengan Layout (judul tab "Pipeline")
        <Layout title="Pipeline">
            {/* Top bar gradient brand */}
            <header className="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
                <div className="max-w-[1600px] px-6 py-5 flex items-center justify-between">
                    <div>
                        {/* Judul & subjudul */}
                        <h1 className="text-2xl font-bold tracking-tight">SYSTEM AI PRENEUR</h1>
                        <p className="text-brand-100 text-sm">Manajemen endorsement &amp; pembayaran</p>
                    </div>
                    <div className="flex items-center gap-2">
                        {/* Tautan report PDF (buka tab baru) */}
                        <a href={'/pipelines/report?category=' + category} target="_blank" rel="noreferrer"
                           className="bg-brand-800/40 hover:bg-brand-800/60 text-white text-sm font-semibold px-4 py-2.5 rounded-xl flex items-center gap-2 transition">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V18a2 2 0 01-2 2z"/></svg>
                            Report PDF
                        </a>
                        {/* Tombol buka modal tambah */}
                        <button onClick={openCreate}
                                className="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Tambah Entri
                        </button>
                        {/* Nama user + tombol logout */}
                        <div className="flex items-center gap-2 pl-2 ml-1 border-l border-white/20">
                            <span className="text-sm text-brand-100 hidden sm:inline">{auth?.user?.name}</span>
                            <button onClick={() => router.post('/logout')}
                                    className="bg-brand-800/40 hover:bg-brand-800/60 text-white text-sm font-semibold px-3 py-2.5 rounded-xl transition flex items-center gap-1.5" title="Keluar">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Keluar
                            </button>
                        </div>
                    </div>
                </div>
                {/* Tab kategori (navigasi Link Inertia) */}
                <div className="max-w-[1600px] px-6 flex gap-1">
                    {Object.entries(categories).map(([ck, cv]) => (
                        <Link key={ck} href={'/pipelines?category=' + ck}
                              className={'px-5 py-2.5 text-sm font-semibold rounded-t-xl transition ' + (category === ck ? 'bg-brand-50 text-brand-700' : 'text-brand-100 hover:bg-brand-800/30')}>
                            {cv} <span className="ml-1 text-xs opacity-70">({counts[ck]})</span>
                        </Link>
                    ))}
                </div>
            </header>

            {/* Area konten utama */}
            <div className="max-w-[1600px] px-6 py-6">

                {/* Info kurs terkini */}
                <div className="flex items-center gap-2 mb-3 text-xs">
                    <span className="inline-flex items-center gap-1.5 bg-white border border-brand-100 rounded-full px-3 py-1 shadow-sm">
                        {/* Titik indikator: hijau bila kurs terkini, amber bila fallback */}
                        <span className={'w-2 h-2 rounded-full ' + (summary.rate !== 16000 ? 'bg-emerald-500' : 'bg-amber-400')}></span>
                        Kurs {summary.rate !== 16000 ? 'terkini' : 'fallback'}:
                        <strong className="text-brand-700">1 USD = {rp(summary.rate)}</strong>
                    </span>
                </div>

                {/* Kartu ringkasan */}
                <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
                    {/* Omzet IDR */}
                    <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                        <p className="text-xs text-slate-500 font-medium">Omzet IDR</p>
                        <p className="text-lg font-bold text-brand-700 mt-1">{rp(summary.total_idr)}</p>
                    </div>
                    {/* Omzet USD */}
                    <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                        <p className="text-xs text-slate-500 font-medium">Omzet USD</p>
                        <p className="text-lg font-bold text-brand-700 mt-1">$ {Number(summary.total_usd || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    </div>
                    {/* Total omzet gabungan (IDR) */}
                    <div className="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                        <p className="text-xs text-brand-100 font-medium">Total Omzet (IDR)</p>
                        <p className="text-lg font-bold mt-1">{rp(summary.grand_idr)}</p>
                        <p className="text-[10px] text-brand-200 mt-0.5">USD dikonversi otomatis</p>
                    </div>
                    {/* Outstanding */}
                    <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                        <p className="text-xs text-slate-500 font-medium">Outstanding (Belum+DP)</p>
                        <p className="text-lg font-bold text-red-600 mt-1">{summary.outstanding} entri</p>
                    </div>
                    {/* Lunas */}
                    <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                        <p className="text-xs text-slate-500 font-medium">Lunas</p>
                        <p className="text-lg font-bold text-emerald-600 mt-1">{summary.lunas} / {summary.total}</p>
                    </div>
                    {/* Progress done */}
                    <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                        <p className="text-xs text-slate-500 font-medium">Progress Done</p>
                        <p className="text-lg font-bold text-brand-700 mt-1">{summary.done} / {summary.total}</p>
                    </div>
                </div>

                {/* Bar filter */}
                <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4 mb-5 flex flex-wrap gap-2 items-center text-sm">
                    {/* Input pencarian: terapkan saat Enter atau blur */}
                    <input value={f.search} placeholder="Cari endorse / notes..."
                           onChange={(e) => setF({ ...f, search: e.target.value })}
                           onKeyDown={(e) => { if (e.key === 'Enter') applyFilters({ search: e.target.value }); }}
                           onBlur={(e) => applyFilters({ search: e.target.value })}
                           className="border border-slate-200 rounded-xl px-3 py-2 w-56 focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none" />
                    {/* Filter account */}
                    <select value={f.account} onChange={(e) => applyFilters({ account: e.target.value })}
                            className="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">Semua Account</option>
                        {Object.entries(accounts).map(([k, v]) => (<option key={k} value={k}>{v}</option>))}
                    </select>
                    {/* Filter progress */}
                    <select value={f.progress} onChange={(e) => applyFilters({ progress: e.target.value })}
                            className="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">Semua Progress</option>
                        {Object.entries(progresses).map(([k, v]) => (<option key={k} value={k}>{v}</option>))}
                    </select>
                    {/* Filter payment */}
                    <select value={f.payment_status} onChange={(e) => applyFilters({ payment_status: e.target.value })}
                            className="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">Semua Payment</option>
                        {Object.entries(payments).map(([k, v]) => (<option key={k} value={k}>{v}</option>))}
                    </select>
                    {/* Filter output */}
                    <select value={f.output} onChange={(e) => applyFilters({ output: e.target.value })}
                            className="border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                        <option value="">Semua Output</option>
                        {outputs.map((out) => (<option key={out.id} value={out.id}>{out.name}</option>))}
                    </select>
                    {/* Reset filter → kembali ke /pipelines */}
                    <Link href="/pipelines" className="text-brand-600 hover:text-brand-800 px-2 font-medium">Reset</Link>
                </div>

                {/* Tabel entri */}
                <div className="bg-white rounded-2xl shadow-sm border border-brand-100 overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="bg-brand-700 text-white text-xs uppercase tracking-wide">
                                <th className="px-4 py-3 text-left">Account</th>
                                <th className="px-4 py-3 text-left">Endorse</th>
                                <th className="px-4 py-3 text-left">Output</th>
                                <th className="px-4 py-3 text-left">Progress</th>
                                <th className="px-4 py-3 text-left">Tgl Posting</th>
                                <th className="px-4 py-3 text-left">Tgl Payment</th>
                                <th className="px-4 py-3 text-left">Payment</th>
                                <th className="px-4 py-3 text-right">IDR</th>
                                <th className="px-4 py-3 text-right">USD</th>
                                <th className="px-4 py-3 text-left">Notes</th>
                                <th className="px-4 py-3 text-left">Ke Gilang</th>
                                <th className="px-4 py-3 text-left">Catatan</th>
                                <th className="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-brand-50">
                            {/* Baris data, atau pesan kosong bila tak ada */}
                            {pipelines.length === 0 ? (
                                <tr><td colSpan="13" className="px-4 py-10 text-center text-slate-400">Belum ada entri.</td></tr>
                            ) : pipelines.map((p) => (
                                <tr key={p.id} className="hover:bg-brand-50/60 transition">
                                    {/* Badge account */}
                                    <td className="px-4 py-2.5">
                                        <span className={'inline-block ' + (ACCOUNT_COLORS[p.account] || 'bg-slate-200 text-slate-700') + ' text-xs font-semibold px-2.5 py-0.5 rounded-full'}>
                                            {accounts[p.account]}
                                        </span>
                                    </td>
                                    {/* Nama endorse */}
                                    <td className="px-4 py-2.5 font-semibold text-slate-700">{p.endorse}</td>
                                    {/* Daftar output */}
                                    <td className="px-4 py-2.5">
                                        <div className="flex flex-wrap gap-1">
                                            {(p.outputs || []).map((out) => (
                                                <span key={out.id} className="text-xs px-2 py-0.5 rounded-full bg-brand-100 text-brand-700 border border-brand-200">{out.name}</span>
                                            ))}
                                        </div>
                                    </td>
                                    {/* Badge progress */}
                                    <td className="px-4 py-2.5">
                                        <span className={'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (PROGRESS_COLORS[p.progress] || 'bg-slate-200 text-slate-700')}>
                                            {progresses[p.progress] || p.progress}
                                        </span>
                                    </td>
                                    {/* Tanggal posting */}
                                    <td className="px-4 py-2.5 text-slate-500">{fmtDate(p.tanggal_posting)}</td>
                                    {/* Tanggal payment */}
                                    <td className="px-4 py-2.5 text-slate-500">{fmtDate(p.tanggal_payment)}</td>
                                    {/* Badge payment */}
                                    <td className="px-4 py-2.5">
                                        <span className={'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (PAYMENT_COLORS[p.payment_status] || 'bg-slate-200 text-slate-700')}>
                                            {payments[p.payment_status]}
                                        </span>
                                    </td>
                                    {/* Nominal IDR */}
                                    <td className="px-4 py-2.5 text-right whitespace-nowrap font-medium">{p.amount_idr ? rp(p.amount_idr) : '—'}</td>
                                    {/* Nominal USD */}
                                    <td className="px-4 py-2.5 text-right whitespace-nowrap font-medium">{p.amount_usd ? '$' + Number(p.amount_usd).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'}</td>
                                    {/* Notes */}
                                    <td className="px-4 py-2.5 text-slate-500 max-w-[200px]">{p.notes || '—'}</td>
                                    {/* Badge ke gilang */}
                                    <td className="px-4 py-2.5">
                                        <span className={'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (GILANG_COLORS[p.ke_gilang] || 'bg-slate-200 text-slate-700')}>
                                            {keGilang[p.ke_gilang]}
                                        </span>
                                    </td>
                                    {/* Catatan */}
                                    <td className="px-4 py-2.5 text-slate-500 max-w-[160px]">{p.catatan || '—'}</td>
                                    {/* Aksi edit & hapus */}
                                    <td className="px-4 py-2.5 text-center whitespace-nowrap">
                                        <div className="flex items-center justify-center gap-1.5">
                                            <button onClick={() => openEdit(p)}
                                                    className="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z"/></svg>
                                                Edit
                                            </button>
                                            <button onClick={() => destroy(p)}
                                                    className="bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {/* Jumlah entri ditampilkan */}
                <p className="text-xs text-slate-400 mt-3">{pipelines.length} entri ditampilkan.</p>
            </div>

            {/* Modal Tambah/Edit */}
            {open && (
                <div className="fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex items-start justify-center overflow-y-auto py-10 z-50">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 border-t-4 border-brand-600">
                        {/* Header modal */}
                        <div className="flex items-center justify-between mb-5">
                            <h2 className="text-lg font-bold text-brand-800">{mode === 'create' ? '+ Tambah Entri' : 'Edit Entri'}</h2>
                            <button type="button" onClick={() => setOpen(false)} className="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                        </div>
                        {/* Form entri */}
                        <form onSubmit={submit} className="grid grid-cols-2 gap-4 text-sm">
                            {/* Kategori */}
                            <label className="block font-medium text-slate-600">Kategori
                                <select value={data.category} onChange={(e) => setData('category', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    {Object.entries(categories).map(([k, v]) => (<option key={k} value={k}>{v}</option>))}
                                </select>
                                {errors.category && <span className="text-xs text-red-600">{errors.category}</span>}
                            </label>
                            {/* Account */}
                            <label className="block font-medium text-slate-600">Account
                                <select value={data.account} onChange={(e) => setData('account', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    {Object.entries(accounts).map(([k, v]) => (<option key={k} value={k}>{v}</option>))}
                                </select>
                                {errors.account && <span className="text-xs text-red-600">{errors.account}</span>}
                            </label>
                            {/* Endorse / produk */}
                            <label className="block col-span-2 font-medium text-slate-600">Endorse / Produk
                                <input value={data.endorse} onChange={(e) => setData('endorse', e.target.value)} required className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {errors.endorse && <span className="text-xs text-red-600">{errors.endorse}</span>}
                            </label>

                            {/* Output (checkbox multi) */}
                            <label className="block col-span-2 font-medium text-slate-600">Output
                                <div className="mt-2 flex flex-wrap gap-3">
                                    {outputs.map((out) => (
                                        <label key={out.id} className="inline-flex items-center gap-1.5 bg-brand-50 border border-brand-100 rounded-lg px-3 py-1.5 cursor-pointer">
                                            <input type="checkbox" value={out.id} className="accent-brand-600"
                                                   checked={data.outputs.includes(out.id)} onChange={() => toggleOutput(out.id)} /> {out.name}
                                        </label>
                                    ))}
                                </div>
                                {errors.outputs && <span className="text-xs text-red-600">{errors.outputs}</span>}
                            </label>

                            {/* Progress */}
                            <label className="block font-medium text-slate-600">Progress
                                <select value={data.progress} onChange={(e) => setData('progress', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    {Object.entries(progresses).map(([k, v]) => (<option key={k} value={k}>{v}</option>))}
                                </select>
                                {errors.progress && <span className="text-xs text-red-600">{errors.progress}</span>}
                            </label>
                            {/* Payment status */}
                            <label className="block font-medium text-slate-600">Payment Status
                                <select value={data.payment_status} onChange={(e) => setData('payment_status', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    {Object.entries(payments).map(([k, v]) => (<option key={k} value={k}>{v}</option>))}
                                </select>
                                {errors.payment_status && <span className="text-xs text-red-600">{errors.payment_status}</span>}
                            </label>

                            {/* Tanggal posting */}
                            <label className="block font-medium text-slate-600">Tanggal Posting
                                <input type="date" value={data.tanggal_posting} onChange={(e) => setData('tanggal_posting', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {errors.tanggal_posting && <span className="text-xs text-red-600">{errors.tanggal_posting}</span>}
                            </label>
                            {/* Tanggal payment */}
                            <label className="block font-medium text-slate-600">Tanggal Payment
                                <input type="date" value={data.tanggal_payment} onChange={(e) => setData('tanggal_payment', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {errors.tanggal_payment && <span className="text-xs text-red-600">{errors.tanggal_payment}</span>}
                            </label>

                            {/* Jumlah IDR + estimasi USD */}
                            <label className="block font-medium text-slate-600">Jumlah IDR
                                <input type="number" step="0.01" value={data.amount_idr} onChange={(e) => setData('amount_idr', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {data.amount_idr > 0 && <span className="text-[11px] text-brand-600">{'≈ $ ' + (data.amount_idr / rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>}
                                {errors.amount_idr && <span className="text-xs text-red-600 block">{errors.amount_idr}</span>}
                            </label>
                            {/* Jumlah USD + estimasi IDR */}
                            <label className="block font-medium text-slate-600">Jumlah USD
                                <input type="number" step="0.01" value={data.amount_usd} onChange={(e) => setData('amount_usd', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {data.amount_usd > 0 && <span className="text-[11px] text-brand-600">{'≈ Rp ' + Math.round(data.amount_usd * rate).toLocaleString('id-ID')}</span>}
                                {errors.amount_usd && <span className="text-xs text-red-600 block">{errors.amount_usd}</span>}
                            </label>

                            {/* Ke gilang */}
                            <label className="block font-medium text-slate-600">Ke Gilang
                                <select value={data.ke_gilang} onChange={(e) => setData('ke_gilang', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    {Object.entries(keGilang).map(([k, v]) => (<option key={k} value={k}>{v}</option>))}
                                </select>
                                {errors.ke_gilang && <span className="text-xs text-red-600">{errors.ke_gilang}</span>}
                            </label>
                            {/* Catatan singkat */}
                            <label className="block font-medium text-slate-600">Catatan
                                <input value={data.catatan} onChange={(e) => setData('catatan', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {errors.catatan && <span className="text-xs text-red-600">{errors.catatan}</span>}
                            </label>

                            {/* Notes panjang */}
                            <label className="block col-span-2 font-medium text-slate-600">Notes
                                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows="2" className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                                {errors.notes && <span className="text-xs text-red-600">{errors.notes}</span>}
                            </label>

                            {/* Aksi modal */}
                            <div className="col-span-2 flex justify-end gap-2 mt-2">
                                <button type="button" onClick={() => setOpen(false)} className="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                                <button type="submit" disabled={processing} className="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </Layout>
    );
}

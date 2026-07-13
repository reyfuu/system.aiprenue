// Halaman User: kelola akun & hak akses (port dari users/index.blade.php)
import { useState } from 'react';                             // state untuk modal & mode
import { router, useForm } from '@inertiajs/react';           // form Inertia + delete router
import Layout from '../Layout';                               // layout bersama (sidebar + toast)

// Warna badge per role (mirror mapping $rc di blade)
const roleColors = {                                          // map role -> kelas badge
    super_admin: 'bg-rose-600 text-white',                   // super admin merah
    admin: 'bg-brand-600 text-white',                        // admin brand
    it: 'bg-violet-600 text-white',                          // IT ungu
    staff: 'bg-slate-200 text-slate-700',                    // staff abu
    editor: 'bg-amber-500 text-white',                       // editor amber
};                                                           // akhir map warna

// Komponen utama, menerima props users & roles dari controller
export default function Users({ users, roles }) {            // destruktur props Inertia
    const [open, setOpen] = useState(false);                 // status modal terbuka
    const [mode, setMode] = useState('create');             // mode form: create/edit
    const [editId, setEditId] = useState(null);             // id user yang sedang diedit

    // Form Inertia dengan field sesuai blade
    const form = useForm({                                   // inisialisasi useForm
        name: '',                                            // field nama
        email: '',                                           // field email
        password: '',                                        // field password
        role: 'staff',                                       // default role staff
    });                                                      // akhir useForm

    // Buka modal untuk tambah user baru
    const openCreate = () => {                               // handler tambah
        setMode('create');                                   // set mode create
        setEditId(null);                                     // tidak ada id edit
        form.reset();                                        // kosongkan field
        form.clearErrors();                                  // bersihkan error lama
        setOpen(true);                                        // tampilkan modal
    };                                                       // akhir openCreate

    // Buka modal untuk edit user tertentu
    const openEdit = (u) => {                               // handler edit
        setMode('edit');                                     // set mode edit
        setEditId(u.id);                                     // simpan id user
        form.clearErrors();                                  // bersihkan error lama
        form.setData({                                       // isi data dari user
            name: u.name,                                    // nama user
            email: u.email,                                  // email user
            password: '',                                    // password kosong (opsional)
            role: u.role,                                    // role user
        });                                                  // akhir setData
        setOpen(true);                                        // tampilkan modal
    };                                                       // akhir openEdit

    // Submit form: create -> POST, edit -> PUT
    const submit = (e) => {                                  // handler submit
        e.preventDefault();                                  // cegah reload halaman
        if (mode === 'create') {                             // jika mode create
            form.post('/users', { onSuccess: () => setOpen(false) }); // kirim POST
        } else {                                             // jika mode edit
            form.put('/users/' + editId, { onSuccess: () => setOpen(false) }); // kirim PUT
        }                                                    // akhir cabang mode
    };                                                       // akhir submit

    // Hapus user dengan konfirmasi
    const destroy = (u) => {                                 // handler hapus
        if (confirm('Hapus user "' + u.name + '"? Tindakan ini tidak bisa dibatalkan.')) { // konfirmasi
            router.delete('/users/' + u.id);                 // kirim DELETE
        }                                                    // akhir konfirmasi
    };                                                       // akhir destroy

    // Ambil user id yang sedang login untuk sembunyikan tombol hapus diri sendiri
    // (server tetap validasi; sini hanya UI seperti @if di blade)
    return (                                                 // mulai render
        <Layout title="User">                               {/* bungkus layout */}
            {/* Header gradient brand */}
            <header className="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
                <div className="px-6 py-5 flex items-center justify-between"> {/* baris header */}
                    <div>                                    {/* blok judul */}
                        <h1 className="text-2xl font-bold tracking-tight">USER</h1> {/* judul */}
                        <p className="text-brand-100 text-sm">Kelola akun & hak akses</p> {/* subjudul */}
                    </div>                                   {/* akhir blok judul */}
                    {/* tombol tambah user */}
                    <button onClick={openCreate}
                            className="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tambah User                          {/* teks tombol */}
                    </button>                                {/* akhir tombol */}
                </div>                                       {/* akhir baris header */}
            </header>                                        {/* akhir header */}

            {/* Body halaman */}
            <div className="px-6 py-6">                      {/* padding konten */}
                <div className="bg-white rounded-2xl shadow-sm border border-brand-100 overflow-x-auto"> {/* kartu tabel */}
                    <table className="min-w-full text-sm">  {/* tabel user */}
                        <thead>                              {/* kepala tabel */}
                            <tr className="bg-brand-700 text-white text-xs uppercase tracking-wide"> {/* baris header */}
                                <th className="px-4 py-3 text-left">Nama / Username</th> {/* kolom nama */}
                                <th className="px-4 py-3 text-left">Email</th> {/* kolom email */}
                                <th className="px-4 py-3 text-left">Status</th> {/* kolom status */}
                                <th className="px-4 py-3 text-center">Aksi</th> {/* kolom aksi */}
                            </tr>                            {/* akhir baris header */}
                        </thead>                             {/* akhir kepala tabel */}
                        <tbody className="divide-y divide-brand-50"> {/* isi tabel */}
                            {users.length === 0 ? (          // jika tidak ada user
                                <tr><td colSpan={4} className="px-4 py-10 text-center text-slate-400">Belum ada user.</td></tr>
                            ) : (                            // jika ada user
                                users.map((u) => (           // loop tiap user
                                    <tr key={u.id} className="hover:bg-brand-50/60 transition"> {/* baris user */}
                                        <td className="px-4 py-2.5 font-semibold text-slate-700">{u.name}</td> {/* nama */}
                                        <td className="px-4 py-2.5 text-slate-500">{u.email}</td> {/* email */}
                                        <td className="px-4 py-2.5">      {/* sel status */}
                                            <span className={'text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (roleColors[u.role] || 'bg-slate-200 text-slate-700')}>
                                                {roles[u.role] || u.role} {/* label role */}
                                            </span>          {/* akhir badge */}
                                        </td>                {/* akhir sel status */}
                                        <td className="px-4 py-2.5 text-center whitespace-nowrap"> {/* sel aksi */}
                                            <div className="flex items-center justify-center gap-1.5"> {/* grup tombol */}
                                                {/* tombol edit */}
                                                <button onClick={() => openEdit(u)}
                                                        className="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">Edit</button>
                                                {/* tombol hapus */}
                                                <button type="button" onClick={() => destroy(u)}
                                                        className="bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                                            </div>           {/* akhir grup tombol */}
                                        </td>                {/* akhir sel aksi */}
                                    </tr>                    // akhir baris user
                                ))                           // akhir loop
                            )}                               {/* akhir kondisi isi */}
                        </tbody>                             {/* akhir isi tabel */}
                    </table>                                 {/* akhir tabel */}
                </div>                                       {/* akhir kartu tabel */}
                <p className="text-xs text-slate-400 mt-3">{users.length} user.</p> {/* jumlah user */}
            </div>                                           {/* akhir body */}

            {/* Modal Tambah/Edit */}
            {open && (                                       // tampil bila modal terbuka
                <div className="fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4"
                     onClick={() => setOpen(false)}>
                    {/* klik backdrop menutup, klik dalam dicegah */}
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border-t-4 border-brand-600"
                         onClick={(e) => e.stopPropagation()}> {/* cegah tutup saat klik dalam */}
                        <div className="flex items-center justify-between mb-5"> {/* header modal */}
                            <h2 className="text-lg font-bold text-brand-800">{mode === 'create' ? 'Tambah User' : 'Edit User'}</h2> {/* judul modal */}
                            <button type="button" onClick={() => setOpen(false)} className="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                        </div>                               {/* akhir header modal */}
                        <form onSubmit={submit} className="space-y-3 text-sm"> {/* form modal */}
                            <label className="block font-medium text-slate-600">Nama / Username {/* label nama */}
                                <input name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required
                                       className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>} {/* error nama */}
                            </label>                         {/* akhir field nama */}
                            <label className="block font-medium text-slate-600">Email {/* label email */}
                                <input type="email" name="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required
                                       className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {form.errors.email && <span className="text-xs text-red-600">{form.errors.email}</span>} {/* error email */}
                            </label>                         {/* akhir field email */}
                            <label className="block font-medium text-slate-600"> {/* label password */}
                                <span>{mode === 'create' ? 'Password' : 'Password (kosongkan bila tidak diubah)'}</span> {/* teks label dinamis */}
                                <input type="password" name="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)}
                                       required={mode === 'create'} autoComplete="new-password"
                                       className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                                {form.errors.password && <span className="text-xs text-red-600">{form.errors.password}</span>} {/* error password */}
                            </label>                         {/* akhir field password */}
                            <label className="block font-medium text-slate-600">Status {/* label role */}
                                <select name="role" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}
                                        className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    {Object.entries(roles).map(([k, v]) => ( // loop opsi role
                                        <option key={k} value={k}>{v}</option> // opsi role
                                    ))}                      {/* akhir loop opsi */}
                                </select>                    {/* akhir select */}
                                {form.errors.role && <span className="text-xs text-red-600">{form.errors.role}</span>} {/* error role */}
                            </label>                         {/* akhir field role */}
                            <div className="flex justify-end gap-2 pt-2"> {/* tombol aksi form */}
                                <button type="button" onClick={() => setOpen(false)} className="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                                <button type="submit" disabled={form.processing} className="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">Simpan</button>
                            </div>                           {/* akhir tombol aksi */}
                        </form>                              {/* akhir form modal */}
                    </div>                                   {/* akhir kotak modal */}
                </div>
            )}{/* akhir kondisi modal */}
        </Layout>                                            // akhir layout
    );                                                       // akhir return
}                                                            // akhir komponen Users

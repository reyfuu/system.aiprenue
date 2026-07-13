// Halaman Kanban — kolom dinamis, drag-drop, label, checklist, + fitur kartu:
// deadline, arsip, deskripsi, attachment, komentar (staff yg ditugasi pun bisa komentar).
import { useEffect, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import Layout from '../Layout';

// Preset label warna (Urgent = penanda mendesak)
const LABEL_PRESETS = [
    { name: 'Urgent', color: 'bg-red-500' },
    { name: 'Penting', color: 'bg-amber-500' },
    { name: 'Review', color: 'bg-purple-500' },
    { name: 'Selesai', color: 'bg-emerald-500' },
    { name: 'Info', color: 'bg-sky-500' },
];

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || ''; // token utk fetch
const todayStr = () => new Date().toISOString().slice(0, 10);                       // 'YYYY-MM-DD' hari ini
const isUrgent = (card) => (card.labels || []).some((l) => l.name === 'Urgent');    // kartu mendesak?
const fmtSize = (b) => (b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'); // ukuran file

export default function Kanban({ category, counts, categories, board, columns, staff, outputs, canManage, currentBoard, showArchived, archivedCount }) {
    const authUser = usePage().props.auth.user;      // user login (utk izin hapus komentar)
    const [cols, setCols] = useState(board);         // kartu per kolom (state lokal)
    useEffect(() => { setCols(board); }, [board]);   // resync saat board prop berubah

    const [q, setQ] = useState('');                  // teks filter
    const [drag, setDrag] = useState({ id: null, from: null }); // state drag
    const [colMenu, setColMenu] = useState(null);    // kolom yg menunya terbuka
    const colNames = Object.fromEntries(columns.map((c) => [c.key, c.name])); // key→nama kolom

    // Filter kartu per kolom
    const filtered = (key) => {
        const list = cols[key] || [];
        const s = q.trim().toLowerCase();
        if (!s) return list;
        return list.filter((c) => c.endorse.toLowerCase().includes(s) || c.code.toLowerCase().includes(s));
    };

    // ---- Drag & drop (nonaktif di mode arsip) ----
    const onDragStart = (id, from) => { if (canManage && !showArchived) setDrag({ id, from }); };
    const onDrop = (to) => {
        if (!canManage || showArchived) return;
        const { id, from } = drag;
        if (id === null || from === to) return;
        const card = (cols[from] || []).find((c) => c.id === id);
        if (!card) return;
        setCols((prev) => ({ ...prev, [from]: prev[from].filter((c) => c.id !== id), [to]: [...prev[to], card] }));
        setDrag({ id: null, from: null });
        fetch(`/pipelines/${id}/progress`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ progress: to }),
        }).catch(() => router.reload());
    };

    // ---- Modal tambah kartu ----
    const [addOpen, setAddOpen] = useState(false);
    const addForm = useForm({ category, progress: columns[0]?.key || 'script', endorse: '', account: 'fk', assigned_to: '', link: '', payment_status: 'belum', ke_gilang: 'belum' });
    const openAdd = (progress) => {
        if (!canManage) return;
        addForm.setData({ category, progress, endorse: '', account: 'fk', assigned_to: '', link: '', payment_status: 'belum', ke_gilang: 'belum' });
        setAddOpen(true);
    };
    const submitAdd = (e) => { e.preventDefault(); addForm.post('/pipelines', { onSuccess: () => setAddOpen(false) }); };

    // ---- Modal DETAIL kartu (dibuka klik kartu; utk semua user) ----
    const [detailId, setDetailId] = useState(null);                              // id kartu dibuka
    const detailCard = detailId ? Object.values(cols).flat().find((c) => c.id === detailId) : null; // kartu aktif
    // Form edit (khusus manager) — field lengkap termasuk deadline & deskripsi
    const editForm = useForm({ category, endorse: '', description: '', account: 'fk', progress: 'script', assigned_to: '', payment_status: 'belum', amount_idr: '', amount_usd: '', link: '', deadline: '', outputs: [], notes: '', ke_gilang: 'belum', labels: [] });
    const openDetail = (card) => {
        setDetailId(card.id);                                                    // buka modal
        if (canManage) {
            editForm.setData({
                category,
                endorse: card.endorse ?? '',
                description: card.description ?? '',
                account: card.account_key ?? 'fk',
                progress: card.progress ?? 'script',
                assigned_to: card.assigned_to ?? '',
                payment_status: card.payment_status ?? 'belum',
                amount_idr: card.amount_idr ?? '',
                amount_usd: card.amount_usd ?? '',
                link: card.link ?? '',
                deadline: card.deadline ?? '',
                outputs: Array.isArray(card.output_ids) ? card.output_ids.map(Number) : [],
                notes: card.notes ?? '',
                ke_gilang: card.ke_gilang ?? 'belum',
                labels: Array.isArray(card.labels) ? card.labels.map((l) => ({ ...l })) : [],
            });
        }
    };
    const submitEdit = (e) => { e.preventDefault(); editForm.put('/pipelines/' + detailId, { preserveScroll: true }); };
    const hasLabel = (color) => editForm.data.labels.some((l) => l.color === color);
    const toggleLabel = (lp) => {
        const i = editForm.data.labels.findIndex((l) => l.color === lp.color);
        const next = [...editForm.data.labels];
        if (i === -1) next.push({ name: lp.name, color: lp.color }); else next.splice(i, 1);
        editForm.setData('labels', next);
    };
    const toggleOutput = (id) => {
        const arr = editForm.data.outputs;
        editForm.setData('outputs', arr.includes(id) ? arr.filter((x) => x !== id) : [...arr, id]);
    };

    // ---- Arsip / hapus kartu ----
    const archiveCard = (card) => {
        if (!canManage) return;
        router.patch(`/pipelines/${card.id}/archive`, {}, { preserveScroll: true, onSuccess: () => setDetailId(null) });
    };
    const deleteCard = (card) => {
        if (!canManage) return;
        if (!confirm(`Hapus kartu "${card.endorse}"? Tindakan ini tidak bisa dibatalkan.`)) return;
        router.delete('/pipelines/' + card.id, { onSuccess: () => setDetailId(null) });
    };

    // ---- Komentar (semua user boleh) ----
    const commentForm = useForm({ body: '' });
    const submitComment = (e) => {
        e.preventDefault();
        if (!commentForm.data.body.trim()) return;
        commentForm.post(`/pipelines/${detailId}/comments`, { preserveScroll: true, onSuccess: () => commentForm.reset('body') });
    };
    const deleteComment = (id) => router.delete(`/comments/${id}`, { preserveScroll: true }); // penulis/manager

    // ---- Lampiran (upload manager; unduh semua) ----
    const attachForm = useForm({ file: null });
    const submitAttach = (e) => {
        e.preventDefault();
        if (!attachForm.data.file) return;
        attachForm.post(`/pipelines/${detailId}/attachments`, { forceFormData: true, preserveScroll: true, onSuccess: () => attachForm.reset('file') });
    };
    const deleteAttachment = (id) => router.delete(`/attachments/${id}`, { preserveScroll: true });

    // ---- Checklist / todo (modal terpisah, seperti sebelumnya) ----
    const [todoTarget, setTodoTarget] = useState(null);
    const [newTodo, setNewTodo] = useState('');
    const todoCard = todoTarget ? (cols[todoTarget.col] || []).find((c) => c.id === todoTarget.id) : null;
    const todoDone = (card) => (card?.todos || []).filter((t) => t.done).length;
    const findCardCol = (id) => Object.keys(cols).find((k) => cols[k].some((c) => c.id === id));
    const openTodo = (card) => { setTodoTarget({ col: findCardCol(card.id), id: card.id }); setNewTodo(''); };
    const saveTodos = (col, id, todos) => {
        setCols((prev) => ({ ...prev, [col]: prev[col].map((c) => (c.id === id ? { ...c, todos } : c)) }));
        fetch(`/pipelines/${id}/todos`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ todos }) }).catch(() => {});
    };
    const toggleTodo = (i) => { if (canManage && todoCard) saveTodos(todoTarget.col, todoTarget.id, todoCard.todos.map((t, idx) => (idx === i ? { ...t, done: !t.done } : t))); };
    const addTodo = (e) => { e.preventDefault(); const t = newTodo.trim(); if (canManage && todoCard && t) { saveTodos(todoTarget.col, todoTarget.id, [...todoCard.todos, { text: t, done: false }]); setNewTodo(''); } };
    const removeTodo = (i) => { if (canManage && todoCard) saveTodos(todoTarget.col, todoTarget.id, todoCard.todos.filter((_, idx) => idx !== i)); };

    // ---- Modal board & kolom ----
    const [boardCreateOpen, setBoardCreateOpen] = useState(false);
    const [boardEditOpen, setBoardEditOpen] = useState(false);
    const [colCreateOpen, setColCreateOpen] = useState(false);
    const [colEditOpen, setColEditOpen] = useState(false);
    const [colEditId, setColEditId] = useState(null);
    const boardForm = useForm({ name: '' });
    const colForm = useForm({ board_key: category, name: '' });
    const submitBoardCreate = (e) => { e.preventDefault(); boardForm.post('/boards', { onSuccess: () => setBoardCreateOpen(false) }); };
    const submitBoardEdit = (e) => { e.preventDefault(); boardForm.put('/boards/' + currentBoard.key, { onSuccess: () => setBoardEditOpen(false) }); };
    const submitColCreate = (e) => { e.preventDefault(); colForm.post('/columns', { onSuccess: () => setColCreateOpen(false) }); };
    const submitColEdit = (e) => { e.preventDefault(); colForm.put('/columns/' + colEditId, { onSuccess: () => setColEditOpen(false) }); };
    const openColEdit = (id, name) => { setColEditId(id); colForm.setData('name', name); setColEditOpen(true); };
    const deleteColumn = (id) => { if (canManage && confirm('Hapus kolom ini? (hanya bila kosong)')) router.delete('/columns/' + id); };
    const deleteBoard = () => { if (confirm(`Hapus board "${currentBoard.name}"? (hanya bila kosong)`)) router.delete('/boards/' + currentBoard.key); };
    const switchBoard = (key) => router.get('/pipelines/kanban', { category: key }, { preserveState: false });
    const toggleArchiveView = () => router.get('/pipelines/kanban', { category, archived: showArchived ? undefined : 1 }, { preserveState: false });

    return (
        <Layout title="Kanban">
            <div className="p-6">
                {/* Toolbar board */}
                <div className="bg-white border border-brand-100 rounded-2xl shadow-sm p-4 mb-3 flex items-center gap-3">
                    <div>
                        <p className="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Board</p>
                        <select value={category} onChange={(e) => switchBoard(e.target.value)} className="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-brand-400 outline-none">
                            {Object.entries(categories).map(([ck, cv]) => (<option key={ck} value={ck}>{cv} · {counts[ck] ?? 0}</option>))}
                        </select>
                    </div>
                    <span className="text-sm text-slate-400 mt-5">{counts[category] ?? 0} task</span>

                    {canManage && !showArchived && (
                        <div className="flex items-center gap-1.5 mt-5">
                            <button onClick={() => { boardForm.setData('name', ''); setBoardCreateOpen(true); }} className="inline-flex items-center gap-1 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" /></svg>
                                Board
                            </button>
                            {currentBoard && (
                                <>
                                    <button onClick={() => { boardForm.setData('name', currentBoard.name); setBoardEditOpen(true); }} title="Ubah nama board" className="p-2 rounded-lg text-slate-400 hover:bg-brand-50 hover:text-brand-600 transition">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15.6 8 16.6l1-3.8 8.6-8.6z" /></svg>
                                    </button>
                                    <button onClick={deleteBoard} title="Hapus board" className="p-2 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 transition">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                                    </button>
                                </>
                            )}
                        </div>
                    )}

                    {/* Toggle lihat arsip */}
                    <button onClick={toggleArchiveView} className={'ml-auto mt-5 inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-3 py-1.5 border transition ' + (showArchived ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50')}>
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4" /></svg>
                        {showArchived ? 'Lihat aktif' : `Arsip (${archivedCount})`}
                    </button>

                    {!canManage && (
                        <span className="mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 bg-slate-100 border border-slate-200 rounded-full px-3 py-1.5" title="Anda hanya bisa melihat & komentar">
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" d="M2.5 12S5 5 12 5s9.5 7 9.5 7-2.5 7-9.5 7-9.5-7-9.5-7z" /></svg>
                            Lihat & komentar
                        </span>
                    )}
                </div>

                {/* Search */}
                <div className="flex items-center gap-3 mb-5">
                    <div>
                        <p className="text-[10px] uppercase tracking-widest text-slate-400 mb-1">Search</p>
                        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Filter cards…" className="bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm w-64 text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-brand-400 outline-none" />
                    </div>
                    <div className="flex items-center gap-2 mt-5 ml-auto">
                        <button onClick={() => setQ('')} className="bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 text-sm px-4 py-2 rounded-lg transition">Clear filters</button>
                        <button onClick={() => router.reload()} className="bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 text-sm px-4 py-2 rounded-lg transition">Refresh</button>
                    </div>
                </div>

                {showArchived && <p className="text-sm text-slate-500 mb-3">Menampilkan kartu yang diarsipkan. Buka kartu untuk mengembalikan.</p>}

                {/* Kolom */}
                <div className="overflow-x-auto pb-4">
                    <div className="flex gap-3 min-w-max">
                        {columns.map((col) => (
                            <div key={col.key} className="w-72 flex-shrink-0 bg-white border border-brand-100 rounded-2xl shadow-sm p-3" onDragOver={(e) => e.preventDefault()} onDrop={() => onDrop(col.key)}>
                                <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center gap-2">
                                        <span className={'w-2.5 h-2.5 rounded-full ' + col.color}></span>
                                        <h2 className="text-sm font-bold text-slate-700">{col.name}</h2>
                                        <span className="text-xs text-slate-400">{filtered(col.key).length}</span>
                                    </div>
                                    {canManage && !showArchived && (
                                        <div className="flex items-center gap-0.5">
                                            <button onClick={() => openAdd(col.key)} title="Tambah task" className="w-6 h-6 flex items-center justify-center rounded-md bg-brand-50 hover:bg-brand-100 text-brand-600 font-bold leading-none transition">+</button>
                                            <div className="relative">
                                                <button onClick={(e) => { e.stopPropagation(); setColMenu(colMenu === col.key ? null : col.key); }} title="Menu kolom" className="w-6 h-6 flex items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 transition">
                                                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 6a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4z" /></svg>
                                                </button>
                                                {colMenu === col.key && (
                                                    <div className="absolute right-0 top-7 z-20 w-36 bg-white border border-brand-100 rounded-xl shadow-lg py-1 text-sm">
                                                        <button onClick={() => { setColMenu(null); openColEdit(col.id, col.name); }} className="w-full text-left px-4 py-2 hover:bg-brand-50 text-slate-600">Ubah nama</button>
                                                        <button onClick={() => { setColMenu(null); deleteColumn(col.id); }} className="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600">Hapus kolom</button>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-2.5 min-h-[120px] rounded-xl p-2 bg-brand-50/60">
                                    {filtered(col.key).map((card) => {
                                        const overdue = card.deadline && card.deadline < todayStr(); // lewat tenggat?
                                        return (
                                            <div
                                                key={card.id}
                                                draggable={canManage && !showArchived}
                                                onDragStart={() => onDragStart(card.id, col.key)}
                                                onClick={() => openDetail(card)}
                                                className={'group bg-white border rounded-xl p-3 shadow-sm hover:shadow-md transition ' + (isUrgent(card) ? 'border-red-300 ring-1 ring-red-200' : 'border-brand-100 hover:border-brand-200') + ' ' + (showArchived ? 'opacity-70 cursor-pointer' : canManage ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer')}
                                            >
                                                {/* Strip label */}
                                                {card.labels && card.labels.length > 0 && (
                                                    <div className="flex flex-wrap gap-1 mb-1.5">
                                                        {card.labels.map((lb, li) => (<span key={li} className={'h-1.5 w-9 rounded-full ' + lb.color} title={lb.name}></span>))}
                                                    </div>
                                                )}
                                                <div className="flex items-start justify-between mb-1">
                                                    <p className="text-[10px] text-slate-400 font-mono">{card.code}</p>
                                                    {canManage && (
                                                        <button onClick={(e) => { e.stopPropagation(); deleteCard(card); }} title="Hapus kartu" className="text-slate-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">
                                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.9 12a2 2 0 01-2 1.9H7.9a2 2 0 01-2-1.9L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
                                                        </button>
                                                    )}
                                                </div>
                                                <p className="font-semibold text-sm text-slate-700 leading-snug mb-2">{card.endorse}</p>

                                                {/* Baris meta: deadline, deskripsi, komentar, lampiran */}
                                                <div className="flex flex-wrap items-center gap-1.5 mb-2">
                                                    {isUrgent(card) && <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-500 text-white">URGENT</span>}
                                                    {card.deadline && (
                                                        <span className={'inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded ' + (overdue ? 'bg-red-100 text-red-700 font-semibold' : 'bg-slate-100 text-slate-600')}>
                                                            <svg className="w-3 h-3" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" /></svg>
                                                            {card.deadline}
                                                        </span>
                                                    )}
                                                    {card.description && <span className="inline-flex items-center text-slate-400" title="Ada deskripsi"><svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h10" /></svg></span>}
                                                    {card.comment_count > 0 && <span className="inline-flex items-center gap-0.5 text-[10px] text-slate-400"><svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 01-13.5 7.8L3 21l1.2-4.5A9 9 0 1121 12z" /></svg>{card.comment_count}</span>}
                                                    {card.attachment_count > 0 && <span className="inline-flex items-center gap-0.5 text-[10px] text-slate-400"><svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>{card.attachment_count}</span>}
                                                </div>

                                                {/* Output tags */}
                                                {card.outputs.length > 0 && (
                                                    <div className="flex flex-wrap gap-1 mb-2">
                                                        {card.outputs.map((o) => (<span key={o} className="text-[10px] px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-700 border border-brand-200">{o}</span>))}
                                                    </div>
                                                )}

                                                {/* Checklist ringkas */}
                                                <button type="button" onClick={(e) => { e.stopPropagation(); openTodo(card); }} className="w-full flex items-center gap-1.5 text-[10px] text-slate-500 hover:text-brand-700 mb-2 group/todo">
                                                    <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7l2 2 4-4" /></svg>
                                                    <span className="font-medium tabular-nums">{card.todos.length ? todoDone(card) + '/' + card.todos.length : 'checklist'}</span>
                                                    {card.todos.length > 0 && (<span className="flex-1 h-1 rounded-full bg-brand-50 overflow-hidden"><span className="block h-full bg-emerald-500 transition-all" style={{ width: Math.round(todoDone(card) / card.todos.length * 100) + '%' }}></span></span>)}
                                                </button>

                                                {/* Badge akun + pembayaran + waktu */}
                                                <div className="flex items-center justify-between text-[10px] mb-1.5">
                                                    <div className="flex items-center gap-1.5">
                                                        <span className={'font-semibold px-2 py-0.5 rounded-full ' + card.account_color}>{card.account}</span>
                                                        <span className={'font-semibold px-2 py-0.5 rounded-full ' + (card.payment_status === 'lunas' ? 'bg-emerald-600 text-white' : card.payment_status === 'dp' ? 'bg-amber-400 text-amber-900' : 'bg-red-600 text-white')}>{card.payment}</span>
                                                    </div>
                                                    <span className="text-slate-400">{card.time}</span>
                                                </div>
                                                {/* PJ + link */}
                                                <div className="flex items-center justify-between gap-2 text-[10px] pt-1.5 border-t border-brand-50">
                                                    {card.assignee ? (
                                                        <span className="flex items-center gap-1 text-slate-500 truncate">
                                                            <svg className="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                                            <span className="truncate font-medium">{card.assignee}</span>
                                                        </span>
                                                    ) : (<span className="text-slate-300 italic">belum ditugaskan</span>)}
                                                    {card.link && (
                                                        <a href={card.link} target="_blank" rel="noreferrer" onClick={(e) => e.stopPropagation()} className="flex items-center gap-0.5 text-brand-600 hover:text-brand-800 font-medium flex-shrink-0">
                                                            <svg className="w-3 h-3" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                                            Link
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                    {filtered(col.key).length === 0 && <p className="text-center text-xs text-slate-400 py-6">— no tasks —</p>}
                                </div>
                            </div>
                        ))}

                        {canManage && !showArchived && (
                            <div className="w-64 flex-shrink-0">
                                <button onClick={() => { colForm.setData({ board_key: category, name: '' }); setColCreateOpen(true); }} className="w-full flex items-center gap-2 bg-white/70 hover:bg-white border border-dashed border-brand-200 hover:border-brand-300 text-slate-500 hover:text-brand-700 rounded-2xl px-4 py-3 text-sm font-semibold transition">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" /></svg>
                                    Add another list
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* ===== Modal DETAIL kartu ===== */}
            {detailId && detailCard && (
                <ModalWrap onClose={() => setDetailId(null)} width="max-w-2xl" align="items-start">
                    <div className="flex items-start justify-between mb-4">
                        <div>
                            <p className="text-[10px] text-slate-400 font-mono">{detailCard.code}</p>
                            <h2 className="text-lg font-bold text-brand-800 flex items-center gap-2">
                                {detailCard.endorse}
                                {isUrgent(detailCard) && <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-500 text-white">URGENT</span>}
                                {detailCard.archived && <span className="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-200 text-slate-600">ARSIP</span>}
                            </h2>
                        </div>
                        <div className="flex items-center gap-2">
                            {canManage && (
                                <button onClick={() => archiveCard(detailCard)} title={detailCard.archived ? 'Kembalikan dari arsip' : 'Arsipkan kartu'} className="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                                    {detailCard.archived ? 'Kembalikan' : 'Arsipkan'}
                                </button>
                            )}
                            <button type="button" onClick={() => setDetailId(null)} className="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                        </div>
                    </div>

                    {canManage ? (
                        /* --- Form edit lengkap (manager) --- */
                        <form onSubmit={submitEdit} className="grid grid-cols-2 gap-3 text-sm mb-2">
                            <label className="col-span-2 block font-medium text-slate-600">Judul / Endorse
                                <input value={editForm.data.endorse} onChange={(e) => editForm.setData('endorse', e.target.value)} required className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            </label>
                            <label className="col-span-2 block font-medium text-slate-600">Deskripsi
                                <textarea value={editForm.data.description || ''} onChange={(e) => editForm.setData('description', e.target.value)} rows="3" placeholder="Detail task…" className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                            </label>
                            <label className="block font-medium text-slate-600">Deadline
                                <input type="date" value={editForm.data.deadline || ''} onChange={(e) => editForm.setData('deadline', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            </label>
                            <label className="block font-medium text-slate-600">Kolom
                                <select value={editForm.data.progress} onChange={(e) => editForm.setData('progress', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    {columns.map((c) => (<option key={c.key} value={c.key}>{c.name}</option>))}
                                </select>
                            </label>
                            <label className="block font-medium text-slate-600">Account
                                <select value={editForm.data.account} onChange={(e) => editForm.setData('account', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    <option value="fk">FK</option>
                                    <option value="ai_preneur">AI Preneur</option>
                                </select>
                            </label>
                            <label className="block font-medium text-slate-600">Penanggung Jawab
                                <select value={editForm.data.assigned_to || ''} onChange={(e) => editForm.setData('assigned_to', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    <option value="">— belum ditugaskan —</option>
                                    {staff.map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}
                                </select>
                            </label>
                            <label className="block font-medium text-slate-600">Payment
                                <select value={editForm.data.payment_status} onChange={(e) => editForm.setData('payment_status', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                    <option value="belum">Belum</option>
                                    <option value="dp">DP</option>
                                    <option value="lunas">Lunas</option>
                                </select>
                            </label>
                            <label className="block font-medium text-slate-600">Jumlah IDR
                                <input type="number" step="0.01" value={editForm.data.amount_idr || ''} onChange={(e) => editForm.setData('amount_idr', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            </label>
                            <label className="block font-medium text-slate-600">Jumlah USD
                                <input type="number" step="0.01" value={editForm.data.amount_usd || ''} onChange={(e) => editForm.setData('amount_usd', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            </label>
                            <label className="col-span-2 block font-medium text-slate-600">Link Video
                                <input type="url" value={editForm.data.link || ''} onChange={(e) => editForm.setData('link', e.target.value)} placeholder="https://…" className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                            </label>
                            <div className="col-span-2">
                                <p className="font-medium text-slate-600 mb-1.5">Output</p>
                                <div className="flex flex-wrap gap-2">
                                    {outputs.map((out) => (
                                        <label key={out.id} className="inline-flex items-center gap-1.5 bg-brand-50 border border-brand-100 rounded-lg px-3 py-1.5 cursor-pointer">
                                            <input type="checkbox" checked={editForm.data.outputs.includes(out.id)} onChange={() => toggleOutput(out.id)} className="accent-brand-600" /> {out.name}
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <div className="col-span-2">
                                <p className="font-medium text-slate-600 mb-1.5">Label</p>
                                <div className="flex flex-wrap gap-2">
                                    {LABEL_PRESETS.map((lp) => (
                                        <button key={lp.color} type="button" onClick={() => toggleLabel(lp)} className={'flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition ' + (hasLabel(lp.color) ? 'border-brand-400 bg-brand-50 text-slate-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50')}>
                                            <span className={'w-3 h-3 rounded-full ' + lp.color}></span><span>{lp.name}</span>{hasLabel(lp.color) && <span>✓</span>}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <label className="col-span-2 block font-medium text-slate-600">Notes
                                <textarea value={editForm.data.notes || ''} onChange={(e) => editForm.setData('notes', e.target.value)} rows="2" className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none"></textarea>
                            </label>
                            <div className="col-span-2 flex justify-end">
                                <button type="submit" disabled={editForm.processing} className="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">Simpan perubahan</button>
                            </div>
                        </form>
                    ) : (
                        /* --- Ringkasan read-only (non-manager) --- */
                        <div className="space-y-2 text-sm mb-2">
                            {detailCard.deadline && <p><span className="font-medium text-slate-600">Deadline:</span> <span className={detailCard.deadline < todayStr() ? 'text-red-600 font-semibold' : 'text-slate-700'}>{detailCard.deadline}</span></p>}
                            {detailCard.assignee && <p><span className="font-medium text-slate-600">PJ:</span> {detailCard.assignee}</p>}
                            {detailCard.labels.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {detailCard.labels.map((lb, li) => (<span key={li} className={'text-[10px] text-white font-semibold px-2 py-0.5 rounded ' + lb.color}>{lb.name}</span>))}
                                </div>
                            )}
                            <div>
                                <p className="font-medium text-slate-600">Deskripsi</p>
                                <p className="text-slate-700 whitespace-pre-line">{detailCard.description || <span className="text-slate-400 italic">Tidak ada deskripsi.</span>}</p>
                            </div>
                            {detailCard.link && <a href={detailCard.link} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline text-sm">Buka link video →</a>}
                        </div>
                    )}

                    {/* --- Lampiran --- */}
                    <div className="border-t border-slate-100 pt-4 mt-2">
                        <p className="font-semibold text-slate-700 mb-2 text-sm">Lampiran ({detailCard.attachments.length})</p>
                        <div className="space-y-1.5 mb-2">
                            {detailCard.attachments.map((a) => (
                                <div key={a.id} className="flex items-center gap-2 text-sm bg-slate-50 rounded-lg px-3 py-2">
                                    <svg className="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                    <a href={a.url} target="_blank" rel="noreferrer" className="flex-1 text-brand-700 hover:underline truncate">{a.name}</a>
                                    <span className="text-[10px] text-slate-400">{fmtSize(a.size)}</span>
                                    {canManage && <button onClick={() => deleteAttachment(a.id)} className="text-slate-300 hover:text-red-500 text-lg leading-none">&times;</button>}
                                </div>
                            ))}
                            {detailCard.attachments.length === 0 && <p className="text-xs text-slate-400">Belum ada lampiran.</p>}
                        </div>
                        {canManage && (
                            <form onSubmit={submitAttach} className="flex items-center gap-2">
                                {/* input asli disembunyikan, dipicu oleh label bergaya tombol */}
                                <input id="attach-file" type="file" onChange={(e) => attachForm.setData('file', e.target.files[0])} className="hidden" />
                                <label htmlFor="attach-file" className="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-semibold transition">
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                    Pilih file
                                </label>
                                {/* nama file terpilih */}
                                <span className="flex-1 text-xs text-slate-500 truncate">{attachForm.data.file ? attachForm.data.file.name : 'Belum ada file dipilih'}</span>
                                <button type="submit" disabled={attachForm.processing || !attachForm.data.file} className="px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold transition disabled:opacity-50">Unggah</button>
                            </form>
                        )}
                        {attachForm.errors.file && <p className="text-xs text-red-600 mt-1">{attachForm.errors.file}</p>}
                    </div>

                    {/* --- Komentar (semua user) --- */}
                    <div className="border-t border-slate-100 pt-4 mt-4">
                        <p className="font-semibold text-slate-700 mb-2 text-sm">Komentar ({detailCard.comments.length})</p>
                        <form onSubmit={submitComment} className="flex gap-2 mb-3">
                            <input value={commentForm.data.body} onChange={(e) => commentForm.setData('body', e.target.value)} placeholder="Tulis komentar…" className="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                            <button type="submit" disabled={commentForm.processing} className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition disabled:opacity-60">Kirim</button>
                        </form>
                        <div className="space-y-2.5 max-h-60 overflow-y-auto">
                            {detailCard.comments.map((c) => (
                                <div key={c.id} className="flex gap-2">
                                    <div className="w-7 h-7 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold flex-shrink-0">{(c.user || '?').charAt(0).toUpperCase()}</div>
                                    <div className="flex-1 bg-slate-50 rounded-xl px-3 py-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-semibold text-slate-700">{c.user || 'User'}<span className="ml-2 font-normal text-slate-400">{c.time}</span></span>
                                            {(c.user_id === authUser.id || canManage) && <button onClick={() => deleteComment(c.id)} className="text-slate-300 hover:text-red-500 text-sm leading-none">&times;</button>}
                                        </div>
                                        <p className="text-sm text-slate-700 whitespace-pre-line">{c.body}</p>
                                    </div>
                                </div>
                            ))}
                            {detailCard.comments.length === 0 && <p className="text-xs text-slate-400">Belum ada komentar.</p>}
                        </div>
                    </div>
                </ModalWrap>
            )}

            {/* ===== Modal tambah task ===== */}
            {addOpen && (
                <ModalWrap onClose={() => setAddOpen(false)} width="max-w-md">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-bold text-brand-800">Tambah Task <span className="text-sm font-normal text-slate-400">· {colNames[addForm.data.progress]}</span></h2>
                        <button type="button" onClick={() => setAddOpen(false)} className="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                    </div>
                    <form onSubmit={submitAdd} className="space-y-3 text-sm">
                        <label className="block font-medium text-slate-600">Judul / Endorse
                            <input value={addForm.data.endorse} onChange={(e) => addForm.setData('endorse', e.target.value)} required autoFocus className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        </label>
                        <label className="block font-medium text-slate-600">Account
                            <select value={addForm.data.account} onChange={(e) => addForm.setData('account', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                <option value="fk">FK</option>
                                <option value="ai_preneur">AI Preneur</option>
                            </select>
                        </label>
                        <label className="block font-medium text-slate-600">Penanggung Jawab (Staff)
                            <select value={addForm.data.assigned_to} onChange={(e) => addForm.setData('assigned_to', e.target.value)} className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                                <option value="">— belum ditugaskan —</option>
                                {staff.map((s) => (<option key={s.id} value={s.id}>{s.name} ({s.role})</option>))}
                            </select>
                        </label>
                        <label className="block font-medium text-slate-600">Link Video (opsional)
                            <input type="url" value={addForm.data.link} onChange={(e) => addForm.setData('link', e.target.value)} placeholder="https://…" className="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none" />
                        </label>
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={() => setAddOpen(false)} className="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                            <button type="submit" disabled={addForm.processing} className="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition disabled:opacity-60">Simpan</button>
                        </div>
                    </form>
                </ModalWrap>
            )}

            {/* ===== Modal checklist ===== */}
            {todoTarget && todoCard && (
                <ModalWrap onClose={() => setTodoTarget(null)} width="max-w-md">
                    <div className="flex items-start justify-between mb-1">
                        <h2 className="text-lg font-bold text-brand-800">Checklist</h2>
                        <button type="button" onClick={() => setTodoTarget(null)} className="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
                    </div>
                    <p className="text-sm text-slate-500 mb-4 truncate">{todoCard.endorse}</p>
                    {todoCard.todos.length > 0 && (
                        <div className="flex items-center gap-2 mb-3">
                            <div className="flex-1 h-2 rounded-full bg-brand-50 overflow-hidden"><div className="h-full bg-emerald-500 transition-all" style={{ width: Math.round(todoDone(todoCard) / todoCard.todos.length * 100) + '%' }}></div></div>
                            <span className="text-xs font-semibold text-slate-500 tabular-nums">{todoDone(todoCard)}/{todoCard.todos.length}</span>
                        </div>
                    )}
                    <div className="space-y-1.5 max-h-64 overflow-y-auto mb-3">
                        {todoCard.todos.map((t, i) => (
                            <div key={i} className="flex items-center gap-2 group/item rounded-lg px-2 py-1.5 hover:bg-brand-50">
                                <input type="checkbox" checked={t.done} onChange={() => toggleTodo(i)} disabled={!canManage} className="accent-emerald-600 w-4 h-4 flex-shrink-0 disabled:opacity-60" />
                                <span className={'flex-1 text-sm ' + (t.done ? 'line-through text-slate-400' : 'text-slate-700')}>{t.text}</span>
                                {canManage && <button type="button" onClick={() => removeTodo(i)} className="text-slate-300 hover:text-red-500 opacity-0 group-hover/item:opacity-100 transition text-lg leading-none">&times;</button>}
                            </div>
                        ))}
                        {todoCard.todos.length === 0 && <p className="text-center text-sm text-slate-400 py-4">Belum ada item.</p>}
                    </div>
                    {canManage && (
                        <form onSubmit={addTodo} className="flex gap-2">
                            <input value={newTodo} onChange={(e) => setNewTodo(e.target.value)} placeholder="Tambah item…" className="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                            <button type="submit" className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition">Tambah</button>
                        </form>
                    )}
                </ModalWrap>
            )}

            {/* ===== Modal board baru ===== */}
            {canManage && boardCreateOpen && (
                <ModalWrap onClose={() => setBoardCreateOpen(false)} width="max-w-sm">
                    <h2 className="text-lg font-bold text-brand-800 mb-4">Board Baru</h2>
                    <form onSubmit={submitBoardCreate} className="space-y-3">
                        <input value={boardForm.data.name} onChange={(e) => boardForm.setData('name', e.target.value)} required autoFocus placeholder="Nama board…" className="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setBoardCreateOpen(false)} className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
                            <button type="submit" className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">Simpan</button>
                        </div>
                    </form>
                </ModalWrap>
            )}

            {/* ===== Modal kolom baru ===== */}
            {canManage && colCreateOpen && (
                <ModalWrap onClose={() => setColCreateOpen(false)} width="max-w-sm">
                    <h2 className="text-lg font-bold text-brand-800 mb-4">Kolom Baru</h2>
                    <form onSubmit={submitColCreate} className="space-y-3">
                        <input value={colForm.data.name} onChange={(e) => colForm.setData('name', e.target.value)} required autoFocus placeholder="Nama kolom…" className="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setColCreateOpen(false)} className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
                            <button type="submit" className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">Simpan</button>
                        </div>
                    </form>
                </ModalWrap>
            )}

            {/* ===== Modal ubah nama kolom ===== */}
            {canManage && colEditOpen && (
                <ModalWrap onClose={() => setColEditOpen(false)} width="max-w-sm">
                    <h2 className="text-lg font-bold text-brand-800 mb-4">Ubah Nama Kolom</h2>
                    <form onSubmit={submitColEdit} className="space-y-3">
                        <input value={colForm.data.name} onChange={(e) => colForm.setData('name', e.target.value)} required autoFocus className="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setColEditOpen(false)} className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
                            <button type="submit" className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">Simpan</button>
                        </div>
                    </form>
                </ModalWrap>
            )}

            {/* ===== Modal ubah nama board ===== */}
            {canManage && currentBoard && boardEditOpen && (
                <ModalWrap onClose={() => setBoardEditOpen(false)} width="max-w-sm">
                    <h2 className="text-lg font-bold text-brand-800 mb-4">Ubah Nama Board</h2>
                    <form onSubmit={submitBoardEdit} className="space-y-3">
                        <input value={boardForm.data.name} onChange={(e) => boardForm.setData('name', e.target.value)} required autoFocus className="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 outline-none" />
                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setBoardEditOpen(false)} className="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm">Batal</button>
                            <button type="submit" className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm">Simpan</button>
                        </div>
                    </form>
                </ModalWrap>
            )}
        </Layout>
    );
}

// Bungkus modal: overlay + kartu tengah, klik luar menutup.
function ModalWrap({ onClose, width, align = 'items-center', children }) {
    return (
        <div className={'fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex justify-center overflow-y-auto z-50 p-4 ' + align + (align === 'items-start' ? ' py-10' : '')} onClick={onClose}>
            <div className={'bg-white rounded-2xl shadow-2xl w-full ' + width + ' p-6 border-t-4 border-brand-600'} onClick={(e) => e.stopPropagation()}>
                {children}
            </div>
        </div>
    );
}

// Halaman Dashboard — ringkasan Pipeline, Kanban, Pembukuan. Port dari dashboard.blade.php.
import { Link } from '@inertiajs/react';
import Layout from '../Layout';

// Helper format Rupiah: 1234567 → "Rp 1.234.567"
const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

// Warna titik/bar per progress (samakan dengan blade lama)
const progressDot = {
    script: 'bg-purple-500',
    editing: 'bg-sky-500',
    progress: 'bg-brand-600',
    pending: 'bg-amber-500',
    done: 'bg-emerald-500',
};

export default function Dashboard({
    rate, total, totalIdr, totalUsd, grandIdr, lunas, outstanding, done,
    perCategory, perProgress, categories, progresses,
}) {
    return (
        <Layout title="Dashboard">
            {/* Header gradient */}
            <header className="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
                <div className="px-6 py-5">
                    <h1 className="text-2xl font-bold tracking-tight">DASHBOARD</h1>
                    <p className="text-brand-100 text-sm">Ringkasan sistem AI Preneur — kurs 1 USD = {rp(rate)}</p>
                </div>
            </header>

            <div className="px-6 py-6 space-y-6">
                {/* Kartu statistik cepat */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {/* Total entri */}
                    <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                        <p className="text-xs text-slate-500 font-medium">Total Entri</p>
                        <p className="text-2xl font-bold text-brand-700 mt-1">{total}</p>
                    </div>
                    {/* Grand omzet */}
                    <div className="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                        <p className="text-xs text-brand-100 font-medium">Grand Omzet (IDR)</p>
                        <p className="text-2xl font-bold mt-1">{rp(grandIdr)}</p>
                    </div>
                    {/* Lunas */}
                    <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                        <p className="text-xs text-slate-500 font-medium">Lunas</p>
                        <p className="text-2xl font-bold text-emerald-600 mt-1">{lunas}<span className="text-sm text-slate-400 font-medium"> / {total}</span></p>
                    </div>
                    {/* Outstanding */}
                    <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
                        <p className="text-xs text-slate-500 font-medium">Outstanding</p>
                        <p className="text-2xl font-bold text-red-600 mt-1">{outstanding}</p>
                    </div>
                </div>

                {/* Tiga kartu ringkasan modul */}
                <div className="grid lg:grid-cols-3 gap-6">
                    {/* Pipeline: hitungan per kategori */}
                    <Link href="/pipelines" className="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-sm font-bold text-slate-700">Pipeline</h2>
                            <span className="text-xs text-brand-600 font-semibold">Lihat →</span>
                        </div>
                        <p className="text-3xl font-bold text-brand-700">{total} <span className="text-sm text-slate-400 font-medium">entri</span></p>
                        <div className="mt-4 space-y-2">
                            {/* Loop kategori board */}
                            {Object.entries(categories).map(([ck, cv]) => (
                                <div key={ck} className="flex items-center justify-between text-sm">
                                    <span className="text-slate-500">{cv}</span>
                                    <span className="font-semibold text-slate-700">{perCategory[ck] ?? 0}</span>
                                </div>
                            ))}
                        </div>
                    </Link>

                    {/* Kanban: bar per progress */}
                    <Link href="/pipelines/kanban" className="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-sm font-bold text-slate-700">Kanban</h2>
                            <span className="text-xs text-brand-600 font-semibold">Lihat →</span>
                        </div>
                        <p className="text-3xl font-bold text-brand-700">{done} <span className="text-sm text-slate-400 font-medium">/ {total} done</span></p>
                        <div className="mt-4 space-y-2.5">
                            {/* Loop progress standar */}
                            {Object.entries(progresses).map(([pk, pv]) => {
                                const c = perProgress[pk] ?? 0;                       // jumlah kartu progress ini
                                const pct = total ? Math.round((c / total) * 100) : 0; // persentase
                                return (
                                    <div key={pk}>
                                        <div className="flex items-center justify-between text-xs mb-1">
                                            <span className="flex items-center gap-1.5 text-slate-500">
                                                <span className={'w-2 h-2 rounded-full ' + (progressDot[pk] || 'bg-slate-400')}></span>{pv}
                                            </span>
                                            <span className="font-semibold text-slate-700">{c}</span>
                                        </div>
                                        <div className="h-1.5 rounded-full bg-brand-50 overflow-hidden">
                                            <div className={'h-full ' + (progressDot[pk] || 'bg-slate-400')} style={{ width: pct + '%' }}></div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </Link>

                    {/* Pembukuan: omzet */}
                    <Link href="/pembukuan" className="block bg-white rounded-2xl shadow-sm border border-brand-100 p-5 hover:border-brand-300 hover:shadow-md transition">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-sm font-bold text-slate-700">Pembukuan</h2>
                            <span className="text-xs text-brand-600 font-semibold">Lihat →</span>
                        </div>
                        <p className="text-3xl font-bold text-brand-700">{rp(grandIdr)}</p>
                        <p className="text-xs text-slate-400 mt-1">Grand total omzet</p>
                        <div className="mt-4 space-y-2 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Omzet IDR</span>
                                <span className="font-semibold text-slate-700">{rp(totalIdr)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Omzet USD</span>
                                <span className="font-semibold text-slate-700">$ {Number(totalUsd || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">Kurs USD→IDR</span>
                                <span className="font-semibold text-slate-700">{rp(rate)}</span>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </Layout>
    );
}

import React from 'react';
import '../lib/charts';
import { Bar, Doughnut } from 'react-chartjs-2';
import { rp, BRAND } from '../lib/format';
import StatCard from './StatCard.jsx';
import RecapTable from './RecapTable.jsx';

export default function Pembukuan({ data }) {
    const { summary, monthly, incomeByCat, expenseByCat, inventory } = data;
    const hasMonthly = monthly.length > 0;
    const labaPositif = summary.laba >= 0;

    // Bar: pemasukan vs pengeluaran per bulan
    const barData = {
        labels: monthly.map((m) => m.label),
        datasets: [
            { label: 'Pemasukan', data: monthly.map((m) => m.pemasukan), backgroundColor: '#059669', borderRadius: 6 },
            { label: 'Pengeluaran', data: monthly.map((m) => m.pengeluaran), backgroundColor: '#e11d48', borderRadius: 6 },
        ],
    };
    const barOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
            tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${rp(c.parsed.y)}` } },
        },
        scales: {
            y: { ticks: { callback: (v) => 'Rp ' + Number(v).toLocaleString('id-ID') }, grid: { color: '#eef2ff' } },
            x: { grid: { display: false } },
        },
    };

    const doughnut = (rows) => ({
        labels: rows.map((r) => r.label),
        datasets: [{ data: rows.map((r) => r.value), backgroundColor: BRAND.bars, borderWidth: 0 }],
    });
    const doughnutOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
            tooltip: { callbacks: { label: (c) => `${c.label}: ${rp(c.parsed)}` } },
        },
        cutout: '58%',
    };

    return (
        <div className="space-y-6">
            {/* Summary */}
            <div className="grid grid-cols-2 xl:grid-cols-4 gap-3">
                <StatCard label="Total Pemasukan" value={rp(summary.totalIn)} />
                <StatCard label="Total Pengeluaran" value={rp(summary.totalOut)} />
                <StatCard label={labaPositif ? 'Laba' : 'Rugi'} value={rp(summary.laba)} accent hint="Pemasukan − Pengeluaran" />
                <StatCard label={`Nilai Inventaris (${summary.invMonthLabel})`} value={rp(summary.invTotal)} />
            </div>

            {/* Bar pemasukan vs pengeluaran */}
            <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                <h2 className="text-sm font-bold text-slate-700 mb-4">Pemasukan vs Pengeluaran per Bulan</h2>
                <div className="h-64">
                    {hasMonthly ? (
                        <Bar data={barData} options={barOpts} />
                    ) : (
                        <p className="text-center text-slate-400 py-20 text-sm">Belum ada data transaksi.</p>
                    )}
                </div>
            </div>

            {/* Komposisi kategori */}
            <div className="grid lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                    <h2 className="text-sm font-bold text-slate-700 mb-4">Pemasukan per Kategori</h2>
                    <div className="h-56">
                        {incomeByCat.length ? <Doughnut data={doughnut(incomeByCat)} options={doughnutOpts} /> : <p className="text-center text-slate-400 py-16 text-sm">Belum ada data.</p>}
                    </div>
                    <RecapTable head="Kategori" rows={incomeByCat} />
                </div>
                <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                    <h2 className="text-sm font-bold text-slate-700 mb-4">Pengeluaran per Kategori</h2>
                    <div className="h-56">
                        {expenseByCat.length ? <Doughnut data={doughnut(expenseByCat)} options={doughnutOpts} /> : <p className="text-center text-slate-400 py-16 text-sm">Belum ada data.</p>}
                    </div>
                    <RecapTable head="Kategori" rows={expenseByCat} />
                </div>
            </div>

            {/* Rekap bulanan + inventaris */}
            <div className="grid lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                    <h2 className="text-sm font-bold text-slate-700 mb-3">Laba/Rugi per Bulan</h2>
                    <div className="overflow-hidden rounded-xl border border-brand-100">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="bg-brand-700 text-white text-xs uppercase tracking-wide">
                                    <th className="px-3 py-2.5 text-left">Bulan</th>
                                    <th className="px-3 py-2.5 text-right">Masuk</th>
                                    <th className="px-3 py-2.5 text-right">Keluar</th>
                                    <th className="px-3 py-2.5 text-right">Laba</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-brand-50">
                                {monthly.length ? monthly.map((m, i) => (
                                    <tr key={i} className="hover:bg-brand-50/60">
                                        <td className="px-3 py-2.5 text-slate-600">{m.label}</td>
                                        <td className="px-3 py-2.5 text-right text-emerald-600">{rp(m.pemasukan)}</td>
                                        <td className="px-3 py-2.5 text-right text-red-600">{rp(m.pengeluaran)}</td>
                                        <td className={'px-3 py-2.5 text-right font-semibold ' + (m.laba >= 0 ? 'text-slate-700' : 'text-red-600')}>{rp(m.laba)}</td>
                                    </tr>
                                )) : <tr><td colSpan={4} className="px-3 py-6 text-center text-slate-400">Belum ada data.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-5">
                    <h2 className="text-sm font-bold text-slate-700 mb-3">Inventaris Barang <span className="font-normal text-slate-400">({summary.invMonthLabel})</span></h2>
                    <div className="overflow-hidden rounded-xl border border-brand-100">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="bg-brand-700 text-white text-xs uppercase tracking-wide">
                                    <th className="px-3 py-2.5 text-left">Barang</th>
                                    <th className="px-3 py-2.5 text-right">Qty</th>
                                    <th className="px-3 py-2.5 text-right">Nilai</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-brand-50">
                                {inventory.length ? inventory.map((it, i) => (
                                    <tr key={i} className="hover:bg-brand-50/60">
                                        <td className="px-3 py-2.5 text-slate-600">{it.name}</td>
                                        <td className="px-3 py-2.5 text-right">{it.qty}</td>
                                        <td className="px-3 py-2.5 text-right font-medium">{rp(it.total)}</td>
                                    </tr>
                                )) : <tr><td colSpan={3} className="px-3 py-6 text-center text-slate-400">Belum ada data.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <p className="text-xs text-slate-400">Dibuat {summary.generated}</p>
        </div>
    );
}

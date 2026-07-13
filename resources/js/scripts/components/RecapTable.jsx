import React from 'react';
import { rp } from '../lib/format';

export default function RecapTable({ head, rows }) {
    return (
        <div className="overflow-hidden rounded-xl border border-brand-100 mt-4">
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="bg-brand-700 text-white text-xs uppercase tracking-wide">
                        <th className="px-4 py-2.5 text-left">{head}</th>
                        <th className="px-4 py-2.5 text-right">Omzet (IDR)</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-brand-50">
                    {rows.length ? (
                        rows.map((r, i) => (
                            <tr key={i} className="hover:bg-brand-50/60">
                                <td className="px-4 py-2.5 text-slate-600">{r.label}</td>
                                <td className="px-4 py-2.5 text-right font-medium">{rp(r.value)}</td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={2} className="px-4 py-6 text-center text-slate-400">Belum ada data.</td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

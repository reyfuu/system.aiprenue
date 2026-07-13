import React from 'react';

export default function StatCard({ label, value, hint, accent = false }) {
    if (accent) {
        return (
            <div className="bg-gradient-to-br from-brand-600 to-brand-700 rounded-2xl shadow-sm p-4 text-white">
                <p className="text-xs text-brand-100 font-medium">{label}</p>
                <p className="text-lg font-bold mt-1">{value}</p>
                {hint && <p className="text-[10px] text-brand-200 mt-0.5">{hint}</p>}
            </div>
        );
    }
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-brand-100 p-4">
            <p className="text-xs text-slate-500 font-medium">{label}</p>
            <p className="text-lg font-bold text-brand-700 mt-1">{value}</p>
            {hint && <p className="text-[10px] text-slate-400 mt-0.5">{hint}</p>}
        </div>
    );
}

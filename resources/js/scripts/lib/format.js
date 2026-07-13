export const rp = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
export const usd = (n) => '$ ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// palet brand (biru) untuk chart
export const BRAND = {
    line: '#2563eb',
    fill: 'rgba(37, 99, 235, 0.12)',
    bars: ['#2563eb', '#7c3aed', '#0891b2', '#f59e0b', '#e11d48', '#059669', '#db2777'],
};

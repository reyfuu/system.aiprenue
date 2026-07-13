// Halaman Inertia Pembukuan: bungkus komponen chart lama dengan layout + header brand.
import Layout from '../Layout'; // Layout bersama (sidebar + toast flash)
import Pembukuan from '../scripts/components/Pembukuan'; // Komponen chart lama (default export, butuh prop `data`)

// payload dari controller: { summary, monthly, incomeByCat, expenseByCat, inventory, reportUrl }
export default function PembukuanPage({ payload }) {
    return (
        <Layout title="Pembukuan"> {/* set judul tab + render sidebar */}
            {/* Header gradient brand seperti blade lama */}
            <header className="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
                <div className="px-6 py-5 flex items-center justify-between"> {/* baris judul + tombol export */}
                    <div> {/* blok teks judul */}
                        <h1 className="text-2xl font-bold tracking-tight">PEMBUKUAN</h1> {/* judul halaman */}
                        <p className="text-brand-100 text-sm">Pemasukan, pengeluaran &amp; inventaris</p> {/* subjudul */}
                    </div>
                    <a href={payload.reportUrl} target="_blank" rel="noreferrer" // link export PDF ke url laporan
                       className="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24"> {/* ikon dokumen */}
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 8.4V18a2 2 0 01-2 2z" /> {/* garis ikon */}
                        </svg>
                        Export PDF {/* label tombol */}
                    </a>
                </div>
            </header>

            {/* Area konten: komponen lama menerima seluruh payload lewat prop `data` */}
            <div className="px-6 py-6"> {/* padding konten */}
                <Pembukuan data={payload} /> {/* render chart + tabel; komponen ambil summary/monthly/incomeByCat/expenseByCat/inventory dari data */}
            </div>
        </Layout>
    );
}

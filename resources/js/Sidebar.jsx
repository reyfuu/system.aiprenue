// Sidebar navigasi — port dari resources/views/partials/sidebar.blade.php.
// Menu digating oleh auth.user.menus (dibagikan HandleInertiaRequests).
import { Link, usePage, router } from '@inertiajs/react';

// Daftar menu: key cocok dengan auth.user.menus, href tujuan, dan path ikon SVG.
const ITEMS = [
    { key: 'dashboard', label: 'Dashboard', href: '/dashboard',        icon: 'M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 13h6v6h-6z' },
    { key: 'pipeline',  label: 'Pipeline',  href: '/pipelines',        icon: 'M3 10h18M3 6h18M3 14h18M3 18h18' },
    { key: 'kanban',    label: 'Kanban',    href: '/pipelines/kanban', icon: 'M4 5h4v14H4zM10 5h4v9h-4zM16 5h4v6h-4z' },
    { key: 'script',    label: 'Script',    href: '/script',           icon: 'M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z' },
    { key: 'pembukuan', label: 'Pembukuan', href: '/pembukuan',        icon: 'M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 011-1z' },
    { key: 'user',      label: 'User',      href: '/users',            icon: 'M17 20h5v-1a4 4 0 00-4-4h-1m-6 5H2v-1a4 4 0 014-4h4a4 4 0 014 4v1zm-2-9a4 4 0 11-8 0 4 4 0 018 0zm7 0a3 3 0 11-6 0 3 3 0 016 0z' },
];

export default function Sidebar() {
    const { auth } = usePage().props;             // auth dari shared props
    const currentUrl = usePage().url;             // path aktif untuk highlight
    const user = auth?.user;                       // user login
    if (!user) return null;                         // guard: tanpa user, tak render

    // Cari menu yg cocok dgn URL, ambil href TERPANJANG (paling spesifik)
    // → /pipelines/kanban tidak ikut menyorot /pipelines
    const matchHref = (href) => currentUrl === href || currentUrl.startsWith(href + '/') || currentUrl.startsWith(href + '?');
    const activeHref = ITEMS.filter((it) => matchHref(it.href))
        .reduce((best, it) => (it.href.length > best.length ? it.href : best), '');

    // Logout: POST /logout via Inertia (CSRF otomatis)
    const logout = (e) => { e.preventDefault(); router.post('/logout'); };

    return (
        <aside className="hidden md:flex flex-col fixed left-0 top-0 h-screen w-56 bg-brand-800 text-brand-100 z-40">
            {/* Header brand */}
            <div className="px-5 py-5 border-b border-white/10">
                <p className="text-white font-bold leading-tight">SYSTEM AI PRENEUR</p>
                <p className="text-[11px] text-brand-200">Pipeline endorsement</p>
            </div>
            {/* Navigasi */}
            <nav className="flex-1 p-3 space-y-1 text-sm">
                {ITEMS.filter((it) => user.menus[it.key]).map((it) => {
                    // Aktif hanya bila href-nya yg paling spesifik cocok
                    const active = it.href === activeHref;
                    return (
                        <Link
                            key={it.key}
                            href={it.href}
                            className={
                                'flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition ' +
                                (active ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10')
                            }
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d={it.icon} />
                            </svg>
                            {it.label}
                        </Link>
                    );
                })}
            </nav>
            {/* Footer: nama user + tombol logout */}
            <div className="p-3 border-t border-white/10 flex items-center justify-between">
                <span className="text-[11px] text-brand-200 truncate">{user.name}</span>
                <form onSubmit={logout}>
                    <button className="text-brand-200 hover:text-white" title="Keluar">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </form>
            </div>
        </aside>
    );
}

@php $cat = $category ?? 'endorse'; @endphp
<aside class="hidden md:flex flex-col fixed left-0 top-0 h-screen w-56 bg-brand-800 text-brand-100 z-40">
    <div class="px-5 py-5 border-b border-white/10">
        <p class="text-white font-bold leading-tight">FK-AI PRENEUR</p>
        <p class="text-[11px] text-brand-200">Pipeline endorsement</p>
    </div>
    <nav class="flex-1 p-3 space-y-1 text-sm">
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('dashboard') ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 13h6v6h-6z"/></svg>
            Dashboard
        </a>
        <a href="{{ route('pipelines.index', ['category' => $cat]) }}"
           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('pipelines.index') ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6h18M3 14h18M3 18h18"/></svg>
            Pipeline
        </a>
        <a href="{{ route('pipelines.kanban', ['category' => $cat]) }}"
           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('pipelines.kanban') ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h4v14H4zM10 5h4v9h-4zM16 5h4v6h-4z"/></svg>
            Kanban
        </a>
        <a href="{{ route('script.index') }}"
           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('script.*') ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
            Script
        </a>
        <a href="{{ route('pembukuan.index') }}"
           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('pembukuan.*') ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 011-1z"/></svg>
            Pembukuan
        </a>
        <a href="{{ route('users.index') }}"
           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('users.*') ? 'bg-white text-brand-700 font-semibold' : 'hover:bg-white/10' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-4-4h-1m-6 5H2v-1a4 4 0 014-4h4a4 4 0 014 4v1zm-2-9a4 4 0 11-8 0 4 4 0 018 0zm7 0a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            User
        </a>
    </nav>
    <div class="p-3 border-t border-white/10 flex items-center justify-between">
        <span class="text-[11px] text-brand-200 truncate">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-brand-200 hover:text-white" title="Keluar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </button>
        </form>
    </div>
</aside>

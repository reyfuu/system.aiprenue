<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>User — Pipeline FK-AI Preneur</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none}</style>
</head>
<body class="bg-brand-50 text-slate-800 min-h-screen" x-data="userApp()">

@include('partials.sidebar')

<div class="md:ml-56">
    <header class="bg-gradient-to-r from-brand-700 to-brand-600 text-white shadow-lg">
        <div class="px-6 py-5 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">USER</h1>
                <p class="text-brand-100 text-sm">Kelola akun & hak akses</p>
            </div>
            <button @click="openCreate()"
                    class="bg-white text-brand-700 hover:bg-brand-50 text-sm font-semibold px-5 py-2.5 rounded-xl shadow flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah User
            </button>
        </div>
    </header>

    <div class="px-6 py-6">
        @if (session('status'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 x-transition.opacity.duration.300ms
                 class="fixed top-5 right-5 z-[70] bg-emerald-600 text-white text-sm px-4 py-3 rounded-xl shadow-lg">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-brand-100 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-brand-700 text-white text-xs uppercase tracking-wide">
                        <th class="px-4 py-3 text-left">Nama / Username</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-50">
                    @forelse ($users as $u)
                        <tr class="hover:bg-brand-50/60 transition">
                            <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $u->name }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $u->email }}</td>
                            <td class="px-4 py-2.5">
                                @php $rc = ['super_admin' => 'bg-rose-600 text-white', 'admin' => 'bg-brand-600 text-white', 'it' => 'bg-violet-600 text-white', 'staff' => 'bg-slate-200 text-slate-700', 'editor' => 'bg-amber-500 text-white'][$u->role] ?? 'bg-slate-200 text-slate-700'; @endphp
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $rc }}">{{ \App\Models\User::ROLES[$u->role] ?? $u->role }}</span>
                            </td>
                            @php $urow = $u->only('id', 'name', 'email', 'role'); @endphp
                            <td class="px-4 py-2.5 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click='openEdit(@json($urow))'
                                            class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">Edit</button>
                                    @if ($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="button"
                                                    @click="confirmDelete($el.closest('form'), '{{ addslashes($u->name) }}')"
                                                    class="bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Belum ada user.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-400 mt-3">{{ $users->count() }} user.</p>
    </div>
</div>

{{-- Modal Tambah/Edit --}}
<div x-show="open" x-cloak style="display:none"
     class="fixed inset-0 bg-brand-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border-t-4 border-brand-600" @click.outside="open=false">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-bold text-brand-800" x-text="mode === 'create' ? 'Tambah User' : 'Edit User'"></h2>
            <button type="button" @click="open=false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
        </div>
        <form :action="formAction" method="POST" class="space-y-3 text-sm">
            @csrf
            <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

            <label class="block font-medium text-slate-600">Nama / Username
                <input name="name" x-model="form.name" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>
            <label class="block font-medium text-slate-600">Email
                <input type="email" name="email" x-model="form.email" required class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>
            <label class="block font-medium text-slate-600">
                <span x-text="mode === 'create' ? 'Password' : 'Password (kosongkan bila tidak diubah)'"></span>
                <input type="password" name="password" x-model="form.password" :required="mode === 'create'" autocomplete="new-password"
                       class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
            </label>
            <label class="block font-medium text-slate-600">Status
                <select name="role" x-model="form.role" class="mt-1 w-full border border-slate-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-brand-400 outline-none">
                    @foreach (\App\Models\User::ROLES as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="open=false" class="px-5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function userApp() {
    return {
        open: false,
        mode: 'create',
        formAction: '{{ route('users.store') }}',
        form: { name: '', email: '', password: '', role: 'staff' },
        openCreate() {
            this.mode = 'create';
            this.formAction = '{{ route('users.store') }}';
            this.form = { name: '', email: '', password: '', role: 'staff' };
            this.open = true;
        },
        openEdit(u) {
            this.mode = 'edit';
            this.formAction = '/users/' + u.id;
            this.form = { name: u.name, email: u.email, password: '', role: u.role };
            this.open = true;
        },
        confirmDelete(form, name) {
            if (confirm('Hapus user "' + name + '"? Tindakan ini tidak bisa dibatalkan.')) {
                form.submit();
            }
        },
    }
}
</script>
</body>
</html>

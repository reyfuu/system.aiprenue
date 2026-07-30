<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    /**
     * Halaman riwayat audit — hanya owner & it.
     * Menampilkan tabel semua aksi mutasi dengan filter: user, aksi, model,
     * rentang tanggal, dan pencarian nama entity.
     */
    public function index(Request $request)
    {
        Gate::allowIf(in_array(auth()->user()?->role, ['owner', 'it'], true));

        $kwargs = $request->validate([
            'user_id'    => ['nullable', 'integer', 'exists:users,id'],
            'action'     => ['nullable', 'string', 'in:create,update,delete,archive,restore,progress,approve,reject'],
            'model_type' => ['nullable', 'string'],
            'search'     => ['nullable', 'string', 'max:100'],
            'dari'       => ['nullable', 'date'],
            'sampai'     => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $query = AuditLog::with('user')->latest('created_at');

        if ($id = $kwargs['user_id'] ?? null) {
            $query->where('user_id', $id);
        }
        if ($a = $kwargs['action'] ?? null) {
            $query->where('action', $a);
        }
        if ($m = $kwargs['model_type'] ?? null) {
            $query->where('model_type', $m);
        }
        if ($q = $kwargs['search'] ?? null) {
            $query->where('model_name', 'like', "%{$q}%");
        }
        if ($d = $kwargs['dari'] ?? null) {
            $query->whereDate('created_at', '>=', $d);
        }
        if ($s = $kwargs['sampai'] ?? null) {
            $query->whereDate('created_at', '<=', $s);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Daftar unik model_type & action untuk dropdown filter
        $modelTypes = AuditLog::distinct()->pluck('model_type')->sort()->values();
        $actions    = AuditLog::distinct()->pluck('action')->sort()->values();
        $users      = User::select('id', 'name', 'role')->orderBy('name')->get();

        return Inertia::render('AuditLog', [
            'logs'       => $logs,
            'users'      => $users,
            'modelTypes' => $modelTypes,
            'actions'    => $actions,
            'filters'    => $kwargs,
        ]);
    }
}

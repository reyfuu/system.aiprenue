<?php

namespace App\Http\Controllers;

use App\Models\OkrObjective;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

// CRUD OKR (Objective + Key Result). Akses menu & batasan mutasi diatur
// EnsureMenuAccess (menu 'okr'; store/update/destroy butuh canManage()).
class OkrController extends Controller
{
    public function index()
    {
        return Inertia::render('Okr', [
            // Objective terbaru dulu, tiap-tiap dengan KR-nya (urut id).
            'objectives' => OkrObjective::with(['keyResults' => fn ($q) => $q->orderBy('id')])
                ->latest('id')
                ->get(),
        ]);
    }

    /** Aturan validasi bersama create & update. Hanya judul objective yang
     *  wajib; sisanya (periode, penanggung jawab, KR) boleh dilengkapi nanti. */
    private function rules(): array
    {
        return [
            'title'                    => 'required|string|max:150',
            'period'                   => 'nullable|string|max:50',
            'owner'                    => 'nullable|string|max:100',
            'deadline'                 => 'nullable|date',
            'description'              => 'nullable|string|max:1000',
            'key_results'              => 'array',
            'key_results.*.title'      => 'required|string|max:150',
            'key_results.*.completed'  => 'boolean',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $objective = DB::transaction(function () use ($data) {
            $objective = OkrObjective::create($data);
            $this->syncKeyResults($objective, $data['key_results'] ?? []);
            return $objective;
        });

        $objective->syncKanbanBoard(); // otomatis buat board Kanban + kartu per KR

        return back()->with('status', 'Objective ditambahkan.');
    }

    public function update(Request $request, OkrObjective $objective)
    {
        $data = $request->validate($this->rules());

        DB::transaction(function () use ($objective, $data) {
            $objective->update($data);
            $this->syncKeyResults($objective, $data['key_results'] ?? []);
        });

        $objective->syncKanbanBoard(); // sinkron board: rename + tambah kartu KR baru + samakan status

        return back()->with('status', 'Objective diperbarui.');
    }

    public function destroy(OkrObjective $objective)
    {
        $objective->delete(); // KR ikut terhapus (cascadeOnDelete)

        return back()->with('status', 'Objective dihapus.');
    }

    /** Ganti-paket KR: hapus lalu tulis ulang. Menghitung selisih tambah/ubah/
     *  hapus lebih rumit tanpa manfaat — satu objective cuma punya sedikit KR
     *  (pola sama dgn AksesController@update). */
    private function syncKeyResults(OkrObjective $objective, array $keyResults): void
    {
        $objective->keyResults()->delete();

        foreach ($keyResults as $kr) {
            $objective->keyResults()->create([
                'title'     => $kr['title'],
                'completed' => $kr['completed'] ?? false,
            ]);
        }
    }
}

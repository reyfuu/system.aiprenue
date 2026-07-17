<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Script;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Pintu masuk agen Daily Script Rave (repo privat, jalan di GitHub Actions).
 *  Bukan route web: tak ada sesi & tak lewat EnsureMenuAccess — gerbangnya token. */
class ScriptIngestController extends Controller
{
    public function store(Request $request)
    {
        $this->pastikanTokenSah($request);

        $data = $request->validate([
            'brand'          => ['required', Rule::in(array_keys(Script::BRANDS))],
            'generated_for'  => ['required', 'date'],
            'scripts'        => ['required', 'array', 'min:1', 'max:100'],
            'scripts.*.title' => ['required', 'string', 'max:255'],
            'scripts.*.body'  => ['required', 'string'],
        ]);

        $tanggal = \Carbon\Carbon::parse($data['generated_for'])->toDateString();

        // Ganti-paket, bukan tambah: workflow GitHub Actions bisa di-rerun manual,
        // dan tanpa ini sekali rerun = 60 naskah kembar untuk hari yang sama.
        // Transaksi supaya tak pernah ada keadaan "yang lama sudah hilang, yang baru
        // belum masuk" kalau insert-nya gagal di tengah.
        $jumlah = DB::transaction(function () use ($data, $tanggal) {
            Script::where('brand', $data['brand'])->where('generated_for', $tanggal)->delete();

            $now = now();
            Script::insert(array_map(fn ($s) => [
                'brand'         => $data['brand'],
                'title'         => $s['title'],
                'body'          => $s['body'],
                'generated_for' => $tanggal,
                'created_at'    => $now,
                'updated_at'    => $now,
            ], $data['scripts']));

            return count($data['scripts']);
        });

        return response()->json([
            'ok'      => true,
            'brand'   => $data['brand'],
            'tanggal' => $tanggal,
            'jumlah'  => $jumlah,
        ], 201);
    }

    /** hash_equals: bandingkan token dgn waktu tetap, jangan `===` (bocor lewat
     *  selisih waktu). Token kosong di .env = tolak — jangan sampai lupa mengisi
     *  malah membuka endpointnya untuk semua orang. */
    private function pastikanTokenSah(Request $request): void
    {
        $token = (string) config('services.script_agent.token');

        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()),
            401, 'Token tidak sah.');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'tipe_order', 'account', 'tanggal_deadline',
        'nama_customer', 'telepon', 'email', 'kota', 'alamat',
        'tipe_pembayaran', 'tanggal_bayar', 'bukti_bayar', 'invoice',
        'total_idr', 'total_usd',
    ];

    protected $casts = [
        'tanggal_deadline' => 'date',
        'tanggal_bayar'    => 'date',
        'total_idr'        => 'decimal:2',
        'total_usd'        => 'decimal:2',
    ];

    // Peta key→label. Dipakai bersama oleh validasi, filter, dropdown form, dan badge tabel.
    public const TIPE_ORDER = [
        'coaching_1on1'       => 'Coaching 1-on-1',
        'coaching_perusahaan' => 'Coaching Perusahaan',
        'endorse'             => 'Endorse',
        'speaker'             => 'Speaker',
        'agency'              => 'Agency',
    ];

    /** Akun tujuan order. Satu sumber dgn Pipeline — jangan bikin daftar kedua
     *  yang bisa melenceng dari yang dipakai kartu sales. */
    public const ACCOUNTS = Pipeline::ACCOUNTS;

    public const TIPE_PEMBAYARAN = ['full' => 'Full', 'dp' => 'DP'];

    // NB: warna badge tipe order ada di Orders/Index.vue (TIPE_COLORS) — literal di
    // .vue supaya kelasnya terbaca scanner Tailwind. Jangan bikin salinannya di sini.

    /** Total order dlm IDR = nominal IDR + nominal USD dikonversi kurs terkini.
     *  Sengaja turunan (bukan kolom): kalau disimpan, nilainya basi begitu kurs berubah. */
    public function totalIdr(float $rate): float
    {
        return (float) $this->total_idr + (float) $this->total_usd * $rate;
    }

    /** Saran kota (514 Indonesia + Singapura/Australia/Miri City).
     *  Hanya saran — kota boleh diketik manual, lihat OrderController@rules. */
    public static function kotaList(): array
    {
        return config('wilayah');
    }
}

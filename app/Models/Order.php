<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'tipe_order', 'prioritas', 'tanggal_deadline',
        'nama_customer', 'telepon', 'kota', 'alamat',
        'tipe_pembayaran', 'tanggal_bayar', 'bukti_bayar', 'total_pembayaran',
    ];

    protected $casts = [
        'tanggal_deadline' => 'date',
        'tanggal_bayar'    => 'date',
        'total_pembayaran' => 'decimal:2',
    ];

    // Peta key→label. Dipakai bersama oleh validasi, filter, dropdown form, dan badge tabel.
    public const TIPE_ORDER = [
        'coaching' => 'Coaching',
        'endorse'  => 'Endorse',
        'speaker'  => 'Speaker',
        'agency'   => 'Agency',
    ];

    public const PRIORITAS = ['normal' => 'Normal', 'urgent' => 'Urgent', 'super_urgent' => 'Super Urgent'];

    public const TIPE_PEMBAYARAN = ['full' => 'Full', 'dp' => 'DP'];

    /** Warna badge per prioritas (kelas Tailwind statis — terbaca scanner). */
    public const PRIORITAS_COLORS = [
        'normal'       => 'bg-slate-500 text-white',
        'urgent'       => 'bg-amber-400 text-amber-900',
        'super_urgent' => 'bg-red-600 text-white',
    ];

    /** Warna badge per tipe order. */
    public const TIPE_COLORS = [
        'coaching' => 'bg-brand-600 text-white',
        'endorse'  => 'bg-emerald-600 text-white',
        'speaker'  => 'bg-amber-600 text-white',
        'agency'   => 'bg-rose-600 text-white',
    ];

    /** Daftar kota/kabupaten (514 Indonesia + Singapura/Australia/Miri City). */
    public static function kotaList(): array
    {
        return config('wilayah');
    }
}

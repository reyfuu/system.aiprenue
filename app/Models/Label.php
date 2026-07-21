<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Definisi label kartu (dikelola owner). Lihat migrasi create_labels_table. */
class Label extends Model
{
    protected $fillable = ['name', 'color'];

    /**
     * Palet warna label. WAJIB subset dari safelist di resources/css/app.css
     * (@source inline). Warna di luar daftar ini tak ter-generate Tailwind di
     * produksi, jadi labelnya akan tampil tanpa warna. Validasi di
     * LabelController mengunci pilihan ke daftar ini.
     */
    public const COLORS = [
        'bg-red-500', 'bg-amber-500', 'bg-emerald-500', 'bg-sky-500', 'bg-purple-500',
        'bg-teal-500', 'bg-indigo-500', 'bg-rose-500', 'bg-slate-500', 'bg-slate-400', 'bg-brand-600',
    ];
}

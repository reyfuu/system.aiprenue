<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Script extends Model
{
    protected $fillable = ['brand', 'title', 'body', 'generated_for', 'source_pdf_path'];

    protected $casts = ['generated_for' => 'date'];

    /** Brand naskah. Key-nya dipakai bersama agen Daily Script Rave — kalau
     *  ditambah di sini, `BRAND_KEY` di scripts/generate.py wajib ikut.
     *  "Freddie Kashawan" ditulis penuh: brand guideline-nya melarang "FK" di
     *  luar logo/konteks informal, dan melarang ejaan "Freedie". */
    public const BRANDS = [
        'raveloux' => 'Raveloux',
        'rave_tailor' => 'Rave Tailor',
        'fk' => 'Freddie Kashawan',
    ];

    public function brandLabel(): string
    {
        return self::BRANDS[$this->brand] ?? $this->brand;
    }
}

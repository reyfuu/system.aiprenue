<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Script extends Model
{
    protected $fillable = ['brand', 'title', 'body', 'generated_for', 'drive_link'];

    protected $casts = ['generated_for' => 'date'];

    /** Brand naskah. Key-nya dipakai bersama agen Daily Script Rave — kalau
     *  ditambah di sini, `BRAND_KEY` di scripts/generate.py wajib ikut. */
    public const BRANDS = [
        'raveloux'    => 'Raveloux',
        'rave_tailor' => 'Rave Tailor',
        'fk'          => 'FK',
    ];

    public function brandLabel(): string
    {
        return self::BRANDS[$this->brand] ?? $this->brand;
    }
}

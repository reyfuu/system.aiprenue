<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $fillable = [
        'comp', 'jenis_postingan', 'kategori', 'referensi', 'inti_pesan',
        'hook_material', 'brief_original', 'opsi_brief', 'script_remake',
        'editor', 'progress', 'tanggal_upload', 'link_hasil_editing',
        'link_b_roll', 'caption', 'link_ai_kata_kunci',
    ];

    protected $casts = ['tanggal_upload' => 'date'];

    /** Tahap produksi yang dipakai dropdown form dan badge tabel. */
    public const PROGRESS = [
        'draft' => 'Draft',
        'script' => 'Script',
        'editing' => 'Editing',
        'review' => 'Review',
        'scheduled' => 'Terjadwal',
        'published' => 'Tayang',
    ];
}

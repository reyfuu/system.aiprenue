<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

/**
 * Menu Upload — publikasi konten ke TikTok, YouTube, Instagram.
 *
 * TEMPLATE dulu. Hanya YouTube yang jalur uploadnya bisa hidup lebih dulu
 * (OAuth scope youtube.upload). TikTok & Instagram placeholder sampai izin/OAuth
 * siap. Sesuai arsitektur Insight: app Laravel tak memanggil API platform
 * sendiri — nanti agen VPS yang mengeksekusi upload; halaman ini kirim draft.
 */
class UploadController extends Controller
{
    public function index()
    {
        return Inertia::render('Upload', [
            // status: 'ready' = jalur bisa dicoba lebih dulu, 'soon' = belum aktif.
            'platforms' => [
                ['key' => 'youtube',   'name' => 'YouTube',   'status' => 'ready'],
                ['key' => 'tiktok',    'name' => 'TikTok',    'status' => 'soon'],
                ['key' => 'instagram', 'name' => 'Instagram', 'status' => 'soon'],
            ],
        ]);
    }
}

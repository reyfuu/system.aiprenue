<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Snapshot harian tingkat akun — untuk grafik pertumbuhan. */
#[Fillable([
    'platform', 'akun', 'nama_akun', 'tanggal',
    'followers', 'media_count', 'reach', 'impressions', 'profile_views', 'link_clicks',
])]
class InsightAccount extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }
}

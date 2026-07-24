<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['key', 'name', 'type', 'section', 'super_admin_only', 'created_by'];

    protected $casts = ['super_admin_only' => 'boolean'];

    /** Pembuat board. null utk board bawaan seeder & board lama. */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Target KPI per kuartal milik board ini. */
    public function quarterTargets()
    {
        return $this->hasMany(BoardQuarterTarget::class, 'board_key', 'key');
    }

    // Route binding pakai `key` (slug), bukan id — frontend selalu kirim key.
    // (Fix bug: PUT/DELETE /boards/{key} sebelumnya 404 karena bind default = id.)
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function pipelines()
    {
        return $this->hasMany(Pipeline::class, 'category', 'key');
    }
}

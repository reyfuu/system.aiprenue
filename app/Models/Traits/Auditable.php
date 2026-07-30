<?php

namespace App\Models\Traits;

use App\Models\AuditLog;

/**
 * Trait yang dipasang pada model Eloquent untuk merekam secara otomatis
 * semua aksi create, update, dan delete ke tabel audit_logs.
 *
 * Untuk aksi di luar create/update/delete (archive, approve, dll.),
 * panggil AuditLog::record() secara manual dari controller.
 *
 * Gunakan dengan menambahkan: use Auditable;
 * pada model yang perlu direkam riwayat perubahannya.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::record('create', $model);
        });

        static::updated(function ($model) {
            $old = array_intersect_key($model->getOriginal(), $model->getChanges());
            $new = $model->getChanges();
            AuditLog::record('update', $model, $old, $new);
        });

        static::deleted(function ($model) {
            AuditLog::record('delete', $model, new: null);
        });
    }
}

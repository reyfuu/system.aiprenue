<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'user_id', 'user_role', 'action', 'model_type', 'model_id',
    'model_name', 'old_values', 'new_values', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    public $timestamps = false;

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Aktor yang melakukan aksi (nullable = sistem/cron).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rekam satu aksi mutasi ke tabel audit_logs.
     *
     * @param string $action    create, update, delete, archive, restore, progress, approve, reject
     * @param Model $model      model yang dimutasi
     * @param array|null $old   nilai sebelum (untuk update)
     * @param array|null $new   nilai setelah
     */
    public static function record(string $action, Model $model, ?array $old = null, ?array $new = null): void
    {
        $req = request();

        static::create([
            'user_id'    => Auth::id(),
            'user_role'  => Auth::user()?->role ?? 'system',
            'action'     => $action,
            'model_type' => get_class($model),
            'model_id'   => $model->getKey(),
            'model_name' => static::resolveName($model),
            'old_values' => $old,
            'new_values' => $new ?? $model->getAttributes(),
            'ip_address' => $req?->ip(),
            'user_agent' => $req?->userAgent(),
        ]);
    }

    /**
     * Cari representasi nama entity dari model. Diprioritaskan: name, title,
     * endorse, judul. Fallback: id.
     */
    private static function resolveName(Model $model): string
    {
        return (string) (
            $model->name
            ?? $model->title
            ?? $model->endorse
            ?? $model->judul
            ?? $model->getKey()
        );
    }
}

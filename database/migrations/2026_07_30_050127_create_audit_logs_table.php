<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel audit log — rekam semua aksi mutasi (create, update, delete, archive,
     * restore, progress, approve, reject) untuk lacak balik dan keamanan.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_role');   // snapshot peran saat aksi
            $table->string('action');      // create, update, delete, archive, restore, progress, approve, reject
            $table->string('model_type');  // App\Models\Pipeline, App\Models\BoardColumn, dll.
            $table->unsignedBigInteger('model_id');
            $table->string('model_name')->nullable(); // judul / representasi entity
            $table->json('old_values')->nullable();    // field & nilai sebelum (hanya untuk update)
            $table->json('new_values')->nullable();    // field & nilai setelah
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

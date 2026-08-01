<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('column_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_column_id')->constrained()->cascadeOnDelete();
            // Delegasi: owner/manager menugaskan item ke seorang staff.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            // Menetap (bukan reset harian): sekali selesai tetap selesai.
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['board_column_id', 'position']);
        });

        // Ganti fitur checklist personal (routine_tasks) yang belum pernah dirilis.
        Schema::dropIfExists('routine_tasks');
    }

    public function down(): void
    {
        Schema::dropIfExists('column_tasks');
    }
};

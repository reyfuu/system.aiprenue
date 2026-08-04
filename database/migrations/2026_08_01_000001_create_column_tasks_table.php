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
            // Reset harian tanpa cron: "selesai" = completed_on == hari ini.
            // Lewat jam 12 malam tanggalnya ganti, jadi centang otomatis kosong
            // lagi. Definisi & delegasi task tetap; hanya status hariannya reset.
            $table->date('completed_on')->nullable();
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

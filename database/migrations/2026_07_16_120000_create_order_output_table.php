<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pivot Order ↔ Output — checkbox "Output" di modal Order.
 *  Bentuknya menyalin `output_pipeline` (nama tabel `order_output` = urutan
 *  alfabetis, konvensi Laravel; kalau tidak, belongsToMany harus disebut manual). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_output')) {
            return;   // skema server pernah menyimpang dari catatan migrasi (impor .sql)
        }

        Schema::create('order_output', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_id')->constrained()->cascadeOnDelete();
            $table->unique(['order_id', 'output_id']);   // satu output sekali per order
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_output');
    }
};

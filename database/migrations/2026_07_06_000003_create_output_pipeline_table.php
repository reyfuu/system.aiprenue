<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('output_pipeline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_id')->constrained()->cascadeOnDelete();
            $table->unique(['pipeline_id', 'output_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('output_pipeline');
    }
};

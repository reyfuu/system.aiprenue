<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mindmaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // pemilik
            $table->string('title')->default('Mindmap Baru');
            $table->json('data')->nullable();          // struktur node mind-elixir (getData())
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mindmaps');
    }
};

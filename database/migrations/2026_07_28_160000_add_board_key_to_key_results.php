<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_results', function (Blueprint $table): void {
            $table->string('board_key')->nullable()->after('source');
            $table->index('board_key');
        });
    }

    public function down(): void
    {
        Schema::table('key_results', function (Blueprint $table): void {
            $table->dropIndex(['board_key']);
            $table->dropColumn('board_key');
        });
    }
};

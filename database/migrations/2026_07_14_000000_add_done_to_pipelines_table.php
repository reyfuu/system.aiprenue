<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->boolean('done')->default(false)->after('progress'); // tandai kartu selesai (ala Trello) tanpa pindah kolom
        });
    }

    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn('done');
        });
    }
};

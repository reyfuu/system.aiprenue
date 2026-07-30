<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->unsignedTinyInteger('group')->default(2)->after('name');
        });

        DB::table('labels')->whereIn('name', ['Process', 'Selesai'])->update(['group' => 1]);
        DB::table('labels')->updateOrInsert(
            ['name' => 'Belum'],
            ['group' => 1, 'color' => 'bg-slate-500', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('labels')->where('name', 'Belum')->delete();
        Schema::table('labels', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }
};

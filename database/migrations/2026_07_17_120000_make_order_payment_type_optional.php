<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'tipe_pembayaran')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('tipe_pembayaran')->nullable()->change());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'tipe_pembayaran')) {
            DB::table('orders')->whereNull('tipe_pembayaran')->update(['tipe_pembayaran' => 'full']);
            Schema::table('orders', fn (Blueprint $table) => $table->string('tipe_pembayaran')->default('full')->change());
        }
    }
};

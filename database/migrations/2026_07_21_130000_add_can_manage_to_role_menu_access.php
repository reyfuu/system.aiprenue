<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Bedakan akses lihat dan CRUD per menu. Untuk saat ini dipakai menu Content. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_menu_access', function (Blueprint $table) {
            $table->boolean('can_manage')->default(false)->after('menu');
        });

        // Pertahankan perilaku lama: role pengelola yang sudah punya akses Content
        // langsung mendapat CRUD setelah migrasi, bukan tiba-tiba turun jadi lihat saja.
        DB::table('role_menu_access')
            ->where('menu', 'content')
            ->whereIn('role', ['owner', 'manager', 'it', 'admin'])
            ->update(['can_manage' => true]);
    }

    public function down(): void
    {
        Schema::table('role_menu_access', function (Blueprint $table) {
            $table->dropColumn('can_manage');
        });
    }
};

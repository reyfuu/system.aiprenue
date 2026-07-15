<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('section')->nullable()->after('name');            // grup galeri "kanban luar" (mis. "Billy Expense")
            $table->boolean('super_admin_only')->default(false)->after('section'); // board khusus super_admin (todo/hrd)
        });

        // Board "todolist" (todo) → hanya super_admin (sesuai permintaan)
        DB::table('categories')->where('key', 'todolist')->update(['super_admin_only' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['section', 'super_admin_only']);
        });
    }
};

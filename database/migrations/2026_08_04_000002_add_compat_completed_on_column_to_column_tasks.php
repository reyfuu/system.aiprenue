<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('column_tasks')) {
            return;
        }

        if (! Schema::hasColumn('column_tasks', 'completed_on')) {
            Schema::table('column_tasks', function (Blueprint $table) {
                $table->date('completed_on')->nullable()->after('title');
            });
        }

        if (Schema::hasColumn('column_tasks', 'completed_at')) {
            $fn = DB::getDriverName() === 'sqlite' ? 'date' : 'DATE';
            DB::table('column_tasks')
                ->whereNull('completed_on')
                ->whereNotNull('completed_at')
                ->update(['completed_on' => DB::raw("{$fn}(completed_at)")]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('column_tasks') && ! Schema::hasColumn('column_tasks', 'completed_at') && Schema::hasColumn('column_tasks', 'completed_on')) {
            Schema::table('column_tasks', function (Blueprint $table) {
                $table->dropColumn('completed_on');
            });
        }
    }
};

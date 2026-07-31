<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('okr_periods', 'omset_target')) {
            Schema::table('okr_periods', function (Blueprint $table) {
                $table->decimal('omset_target', 15, 2)->nullable()->after('title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('okr_periods', 'omset_target')) {
            Schema::table('okr_periods', function (Blueprint $table) {
                $table->dropColumn('omset_target');
            });
        }
    }
};

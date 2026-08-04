<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('base_salary', 15, 2)->default(0)->after('role');
            $table->decimal('meal_allowance', 15, 2)->default(0)->after('base_salary');
            $table->decimal('overtime_rate_per_hour', 15, 2)->default(0)->after('meal_allowance');
            $table->decimal('late_penalty_per_minute', 8, 2)->default(0)->after('overtime_rate_per_hour');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'base_salary',
                'meal_allowance',
                'overtime_rate_per_hour',
                'late_penalty_per_minute',
            ]);
        });
    }
};


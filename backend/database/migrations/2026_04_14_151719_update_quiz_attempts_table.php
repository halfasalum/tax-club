// database/migrations/2026_04_15_000002_update_quiz_attempts_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->integer('total_points_earned')->default(0);
            $table->boolean('is_best_attempt')->default(false);
            $table->integer('time_spent_seconds')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn(['total_points_earned', 'is_best_attempt', 'time_spent_seconds']);
        });
    }
};
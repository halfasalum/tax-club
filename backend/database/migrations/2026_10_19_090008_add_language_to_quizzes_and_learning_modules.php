<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quizzes and modules can be written in Kiswahili or English, so
     * members see them in their own language first. Empty means any.
     */
    public function up(): void
    {
        foreach (['quizzes', 'learning_modules'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('language', 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['quizzes', 'learning_modules'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }
    }
};

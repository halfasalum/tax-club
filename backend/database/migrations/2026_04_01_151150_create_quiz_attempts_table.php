<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->uuid('attempt_id')->primary();
            $table->uuid('user_id');
            $table->uuid('quiz_id');
            $table->integer('score');
            $table->integer('max_score');
            $table->timestamp('attempted_at')->useCurrent();

            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->cascadeOnDelete();

            $table->foreign('quiz_id')
                  ->references('quiz_id')
                  ->on('quizzes')
                  ->cascadeOnDelete();

            // Composite index for leaderboard queries
            $table->index(['user_id', 'quiz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
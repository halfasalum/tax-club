// database/migrations/2026_04_15_000001_create_quiz_questions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->uuid('question_id')->primary();
            $table->uuid('quiz_id');
            $table->text('question_text');
            $table->string('question_type')->default('multiple_choice'); // multiple_choice, true_false
            $table->integer('points')->default(1);
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('quiz_id')->references('quiz_id')->on('quizzes')->onDelete('cascade');
            $table->index('quiz_id');
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->uuid('answer_id')->primary();
            $table->uuid('question_id');
            $table->text('answer_text');
            $table->boolean('is_correct')->default(false);
            $table->integer('order_index')->default(0);
            $table->timestamps();
            
            $table->foreign('question_id')->references('question_id')->on('quiz_questions')->onDelete('cascade');
            $table->index('question_id');
        });

        Schema::create('quiz_user_responses', function (Blueprint $table) {
            $table->uuid('response_id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->uuid('answer_id')->nullable();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('points_earned')->default(0);
            $table->timestamps();
            
            $table->foreign('attempt_id')->references('attempt_id')->on('quiz_attempts')->onDelete('cascade');
            $table->foreign('question_id')->references('question_id')->on('quiz_questions');
            $table->foreign('answer_id')->references('answer_id')->on('quiz_answers');
            $table->index('attempt_id');
        });

        // Add points column to users table
        Schema::table('users', function (Blueprint $table) {
            $table->integer('total_points')->default(0);
            $table->integer('points_earned_this_month')->default(0);
            $table->timestamp('points_last_reset')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_user_responses');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_questions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_points', 'points_earned_this_month', 'points_last_reset']);
        });
    }
};
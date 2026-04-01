<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quizzes table — required before quiz_attempts due to FK dependency.
     * The database design references quizzes.quiz_id from quiz_attempts.
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->uuid('quiz_id')->primary();
            $table->uuid('content_id')->nullable();
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->integer('total_marks')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('content_id')
                  ->references('content_id')
                  ->on('content')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
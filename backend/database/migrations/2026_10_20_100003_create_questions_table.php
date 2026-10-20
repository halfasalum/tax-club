<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ask TRA: member questions, expert answers, review and the published
     * knowledge base (COM-REQ01-04).
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('question_id')->primary();
            $table->string('reference', 20)->unique();
            $table->uuid('user_id');
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->string('subject', 200);
            $table->text('body');
            $table->string('language', 2)->default('sw');
            $table->boolean('allow_publish')->default(true);
            $table->string('status', 20)->default('submitted');
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('answer')->nullable();
            $table->uuid('answered_by')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('slug', 200)->nullable()->unique();
            $table->string('public_question', 300)->nullable();
            $table->text('public_answer')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

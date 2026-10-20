<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feedback, suggestions and concerns as numbered cases with a history
     * (FBK-REQ01-04).
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('feedback_id')->primary();
            $table->string('reference', 20)->unique();
            $table->uuid('user_id');
            $table->string('category', 30);
            $table->string('subject', 200);
            $table->text('message');
            $table->string('related_type', 30)->nullable();
            $table->string('related_id', 40)->nullable();
            $table->string('channel', 10)->default('web');
            $table->string('status', 20)->default('submitted');
            $table->string('priority', 10)->default('normal');
            $table->string('area', 30)->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['status', 'priority']);
            $table->index(['assigned_to', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('feedback_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('feedback_id');
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->boolean('internal')->default(false);
            $table->uuid('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('feedback_id')->references('feedback_id')->on('feedback')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_events');
        Schema::dropIfExists('feedback');
    }
};

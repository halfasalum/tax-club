<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reports, moderation cases, actions and restrictions (SAF-REQ01-06,
     * COM-REQ07/08).
     */
    public function up(): void
    {
        Schema::create('moderation_cases', function (Blueprint $table) {
            $table->uuid('case_id')->primary();
            $table->string('reference', 20)->unique();
            $table->string('subject_type', 30);
            $table->string('subject_id', 40);
            $table->uuid('subject_user_id')->nullable();
            $table->string('category', 30);
            $table->string('source', 20)->default('report');
            $table->string('status', 20)->default('open');
            $table->string('priority', 10)->default('normal');
            $table->boolean('safeguarding')->default(false);
            $table->boolean('involves_minor')->default(false);
            $table->uuid('assigned_to')->nullable();
            $table->unsignedInteger('reports_count')->default(0);
            $table->string('outcome', 30)->nullable();
            $table->text('outcome_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'priority']);
        });

        Schema::create('moderation_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id');
            $table->uuid('reporter_id')->nullable();
            $table->string('category', 30);
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('case_id')->references('case_id')->on('moderation_cases')->cascadeOnDelete();
            $table->index(['reporter_id', 'created_at']);
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id')->nullable();
            $table->string('action', 30);
            $table->string('target_type', 30)->nullable();
            $table->string('target_id', 40)->nullable();
            $table->uuid('target_user_id')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('internal')->default(true);
            $table->uuid('performed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('case_id');
            $table->index(['target_user_id', 'created_at']);
        });

        Schema::create('user_restrictions', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('type', 20);
            $table->text('reason');
            $table->uuid('case_id')->nullable();
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->uuid('lifted_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_restrictions');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('moderation_reports');
        Schema::dropIfExists('moderation_cases');
    }
};

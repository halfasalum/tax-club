<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Competitions (CMP-REQ01-05), rewards (RWD-REQ01/02) and digital
     * certificates (CER-REQ01/02).
     */
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->uuid('competition_id')->primary();
            $table->string('code', 12)->unique();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('description');
            $table->text('rules')->nullable();
            $table->string('type', 20)->default('quiz');
            $table->uuid('quiz_id')->nullable();
            $table->string('submission_kind', 20)->nullable();
            $table->json('audience')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('winners_count')->default(3);
            $table->string('editorial_status', 20)->default('draft');
            $table->string('publication_status', 20)->default('not_published');
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->string('results_status', 20)->default('pending');
            $table->uuid('results_submitted_by')->nullable();
            $table->uuid('results_approved_by')->nullable();
            $table->timestamp('results_published_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['publication_status', 'starts_at']);
        });

        Schema::create('competition_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('competition_id');
            $table->uuid('user_id');
            $table->string('status', 20)->default('registered');
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            $table->text('submission_text')->nullable();
            $table->string('submission_url', 500)->nullable();
            $table->string('submission_file', 300)->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->foreign('competition_id')->references('competition_id')->on('competitions')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['competition_id', 'user_id']);
        });

        Schema::create('competition_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('competition_entries')->cascadeOnDelete();
            $table->uuid('judge_id');
            $table->decimal('score', 5, 2);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['entry_id', 'judge_id']);
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name_sw', 150);
            $table->string('name_en', 150);
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->unsignedInteger('value_tzs')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('needs_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('competition_prizes', function (Blueprint $table) {
            $table->id();
            $table->uuid('competition_id');
            $table->unsignedSmallInteger('rank_from');
            $table->unsignedSmallInteger('rank_to');
            $table->foreignId('reward_id')->constrained('rewards')->cascadeOnDelete();

            $table->foreign('competition_id')->references('competition_id')->on('competitions')->cascadeOnDelete();
        });

        Schema::create('reward_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->constrained('rewards');
            $table->uuid('user_id');
            $table->string('source_type', 30)->nullable();
            $table->string('source_id', 40)->nullable();
            $table->string('reason', 300);
            $table->string('status', 20);
            $table->uuid('awarded_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_note')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['status', 'created_at']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('certificate_id')->primary();
            $table->string('number', 24)->unique();
            $table->string('token', 32)->unique();
            $table->uuid('user_id');
            $table->string('kind', 30);
            $table->string('title_sw', 200);
            $table->string('title_en', 200);
            $table->string('subject', 250);
            $table->string('source_type', 30)->nullable();
            $table->string('source_id', 40)->nullable();
            $table->timestamp('issued_at');
            $table->uuid('issued_by')->nullable();
            $table->string('status', 10)->default('valid');
            $table->text('revoked_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'kind', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('reward_awards');
        Schema::dropIfExists('competition_prizes');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('competition_scores');
        Schema::dropIfExists('competition_entries');
        Schema::dropIfExists('competitions');
    }
};

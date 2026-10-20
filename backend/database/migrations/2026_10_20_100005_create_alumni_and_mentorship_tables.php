<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alumni profiles, directory connections and stories (ALM-REQ01-03),
     * and mentorship (MNT-REQ01-06).
     */
    public function up(): void
    {
        Schema::create('alumni_profiles', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->string('occupation', 150)->nullable();
            $table->string('organization', 150)->nullable();
            $table->string('field', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('skills', 300)->nullable();
            $table->string('linkedin_url', 300)->nullable();
            $table->boolean('show_in_directory')->default(false);
            $table->boolean('open_to_mentoring')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('alumni_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('requester_id');
            $table->uuid('recipient_id');
            $table->string('message', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['requester_id', 'recipient_id']);
            $table->index(['recipient_id', 'status']);
        });

        Schema::create('alumni_stories', function (Blueprint $table) {
            $table->uuid('story_id')->primary();
            $table->uuid('user_id');
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('body');
            $table->string('kind', 20)->default('story');
            $table->string('editorial_status', 20)->default('draft');
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('mentor_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->unique();
            $table->json('expertise');
            $table->text('bio');
            $table->string('availability', 300)->nullable();
            $table->unsignedSmallInteger('max_mentees')->default(2);
            $table->boolean('mentors_minors')->default(false);
            $table->string('status', 20)->default('submitted');
            $table->timestamp('declaration_at')->nullable();
            $table->string('background_check', 20)->default('not_required');
            $table->uuid('background_checked_by')->nullable();
            $table->timestamp('background_checked_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::create('mentorship_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('mentee_id');
            $table->json('interests');
            $table->text('goals');
            $table->string('mode', 20)->default('either');
            $table->boolean('guardian_consent')->default(false);
            $table->uuid('consent_confirmed_by')->nullable();
            $table->timestamp('consent_confirmed_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->foreign('mentee_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['status', 'created_at']);
        });

        Schema::create('mentorships', function (Blueprint $table) {
            $table->id();
            $table->uuid('mentor_id');
            $table->uuid('mentee_id');
            $table->foreignId('request_id')->nullable()->constrained('mentorship_requests')->nullOnDelete();
            $table->uuid('matched_by')->nullable();
            $table->uuid('supervisor_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('outcome', 20)->nullable();
            $table->text('closing_note')->nullable();
            $table->unsignedTinyInteger('mentee_rating')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->foreign('mentor_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('mentee_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['status', 'last_activity_at']);
        });

        Schema::create('mentorship_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentorship_id')->constrained('mentorships')->cascadeOnDelete();
            $table->date('held_on');
            $table->string('mode', 20);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('topic', 200);
            $table->text('summary')->nullable();
            $table->boolean('milestone')->default(false);
            $table->uuid('logged_by');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_sessions');
        Schema::dropIfExists('mentorships');
        Schema::dropIfExists('mentorship_requests');
        Schema::dropIfExists('mentor_profiles');
        Schema::dropIfExists('alumni_stories');
        Schema::dropIfExists('alumni_connections');
        Schema::dropIfExists('alumni_profiles');
    }
};

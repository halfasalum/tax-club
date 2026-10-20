<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moderated discussion spaces, collaboration groups and posts
     * (COM-REQ05, COM-REQ06, COM-REQ09).
     */
    public function up(): void
    {
        Schema::create('community_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->uuid('institution_id')->nullable();
            $table->uuid('event_id')->nullable();
            $table->uuid('competition_id')->nullable();
            $table->json('audience')->nullable();
            $table->boolean('members_only')->default(false);
            $table->boolean('moderators_post_only')->default(false);
            $table->string('status', 20)->default('active');
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('community_space_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('community_spaces')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('role', 20)->default('member');
            $table->uuid('added_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['space_id', 'user_id']);
        });

        Schema::create('community_posts', function (Blueprint $table) {
            $table->uuid('post_id')->primary();
            $table->foreignId('space_id')->constrained('community_spaces')->cascadeOnDelete();
            $table->uuid('user_id');
            $table->uuid('parent_id')->nullable();
            $table->string('title', 200)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('published');
            $table->string('held_reason', 200)->nullable();
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['space_id', 'parent_id', 'status', 'last_activity_at']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('community_space_members');
        Schema::dropIfExists('community_spaces');
    }
};

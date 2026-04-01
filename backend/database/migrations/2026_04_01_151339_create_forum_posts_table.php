<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forum posts table — referenced in ERD (users → forum_posts).
     */
    public function up(): void
    {
        Schema::create('forum_posts', function (Blueprint $table) {
            $table->uuid('post_id')->primary();
            $table->uuid('user_id');
            $table->uuid('parent_post_id')->nullable(); // for threaded replies
            $table->string('title', 300)->nullable();   // null for replies
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->cascadeOnDelete();

            $table->foreign('parent_post_id')
                  ->references('post_id')
                  ->on('forum_posts')
                  ->nullOnDelete();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured learning modules and version-aware progress (LRN-REQ06).
     */
    public function up(): void
    {
        Schema::create('learning_modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 200);
            $table->text('summary')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->string('level', 20)->default('beginner');
            $table->json('audience')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('learning_module_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('learning_modules')->cascadeOnDelete();
            $table->uuid('content_id');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_required')->default(true);

            $table->foreign('content_id')->references('content_id')->on('content')->cascadeOnDelete();
            $table->unique(['module_id', 'content_id']);
        });

        Schema::create('module_completions', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->foreignId('module_id')->constrained('learning_modules')->cascadeOnDelete();
            $table->timestamp('completed_at');

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'module_id']);
        });

        Schema::table('user_progress', function (Blueprint $table) {
            $table->unsignedInteger('content_version')->nullable()->after('content_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            $table->dropColumn('content_version');
        });

        Schema::dropIfExists('module_completions');
        Schema::dropIfExists('learning_module_items');
        Schema::dropIfExists('learning_modules');
    }
};

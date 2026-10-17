<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Learning content with categories, targeting, review and versions
     * (LRN-REQ01-04, LRN-REQ08).
     *
     * The content row holds the live, published fields that members and
     * the public website read. Every change is a numbered revision in
     * content_versions that goes through review; publishing an approved
     * revision copies it onto the content row.
     */
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name_sw', 120);
            $table->string('name_en', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('content', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('content_id');
            $table->foreignId('category_id')->nullable()->after('summary')->constrained('content_categories')->nullOnDelete();
            $table->string('level', 20)->default('beginner')->after('category_id');
            $table->string('language', 5)->default('sw')->after('level');
            $table->longText('body_markdown')->nullable()->after('body_text');
            $table->text('objectives')->nullable()->after('body_markdown');
            $table->string('media_path')->nullable()->after('file_url');
            $table->string('keywords', 300)->nullable()->after('media_path');
            $table->json('audience')->nullable()->after('keywords');
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('audience');
            $table->string('publication_status', 20)->default('not_published')->after('is_public');
            $table->timestamp('publish_at')->nullable()->after('published_at');
            $table->timestamp('expires_at')->nullable()->after('publish_at');
            $table->timestamp('archived_at')->nullable()->after('expires_at');
            $table->unsignedInteger('current_version')->nullable()->after('archived_at');
            $table->timestamp('review_due_at')->nullable()->after('current_version');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['publication_status', 'publish_at']);
        });

        Schema::table('content', function (Blueprint $table) {
            $table->string('content_type', 30)->default('article')->change();
        });

        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('content_id');
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->json('fields');
            $table->text('change_note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('content_id')->references('content_id')->on('content')->cascadeOnDelete();
            $table->unique(['content_id', 'version']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('editorial_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 30);
            $table->string('subject_id', 64);
            $table->unsignedInteger('version')->nullable();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->uuid('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });

        // Prototype content becomes version 1; active items stay published.
        $sequence = 0;
        DB::table('content')->orderBy('published_at')->orderBy('content_id')->each(function (object $row) use (&$sequence) {
            $sequence++;
            $live = $row->is_active && $row->published_at !== null;
            $fields = [
                'title' => $row->title,
                'summary' => $row->summary,
                'content_type' => $row->content_type === 'quiz' ? 'article' : $row->content_type,
                'body_text' => $row->body_text,
                'body_markdown' => $row->body_text,
                'objectives' => null,
                'file_url' => $row->file_url,
                'media_path' => null,
                'category_id' => null,
                'level' => 'beginner',
                'language' => 'sw',
                'keywords' => null,
                'audience' => null,
                'estimated_minutes' => null,
                'is_public' => (bool) $row->is_public,
            ];

            DB::table('content_versions')->insert([
                'content_id' => $row->content_id,
                'version' => 1,
                'status' => $live ? 'published' : 'approved',
                'fields' => json_encode($fields),
                'change_note' => 'Imported from the prototype',
                'published_at' => $live ? $row->published_at : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('content')->where('content_id', $row->content_id)->update([
                'code' => sprintf('LC-%04d', $sequence),
                'content_type' => $fields['content_type'],
                'publication_status' => $live ? 'published' : 'not_published',
                'current_version' => $live ? 1 : null,
                'review_due_at' => $live ? now()->addYear() : null,
                'created_at' => $row->published_at ?? now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_events');
        Schema::dropIfExists('content_versions');

        Schema::table('content', function (Blueprint $table) {
            $table->dropIndex(['publication_status', 'publish_at']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'level', 'language', 'body_markdown', 'objectives', 'media_path', 'keywords', 'audience', 'estimated_minutes',
                'publication_status', 'publish_at', 'expires_at', 'archived_at', 'current_version', 'review_due_at',
                'created_at', 'updated_at',
            ]);
        });

        Schema::dropIfExists('content_categories');
    }
};

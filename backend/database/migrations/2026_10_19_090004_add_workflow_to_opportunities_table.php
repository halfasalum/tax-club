<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Opportunities with more types, eligibility and audience, review,
     * scheduling, closing and archiving (OPP-REQ01-06).
     */
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('type', 30)->default('internship')->change();
            $table->string('code', 12)->nullable()->unique()->after('opportunity_id');
            $table->string('slug', 240)->nullable()->unique()->after('title');
            $table->string('location', 200)->nullable();
            $table->string('region', 60)->nullable();
            $table->string('work_mode', 20)->nullable();
            $table->text('eligibility')->nullable();
            $table->json('audience')->nullable();
            $table->string('apply_method', 20)->default('link');
            $table->string('apply_url', 500)->nullable();
            $table->string('apply_email', 200)->nullable();
            $table->text('apply_instructions')->nullable();
            $table->string('attachment_path', 300)->nullable();
            $table->string('attachment_name', 200)->nullable();

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
            $table->text('status_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->index(['publication_status', 'deadline']);
        });

        DB::table('opportunities')->orderBy('created_at')->get(['opportunity_id', 'is_active', 'title'])->each(function (object $row, int $index) {
            DB::table('opportunities')->where('opportunity_id', $row->opportunity_id)->update([
                'code' => sprintf('OP-%04d', $index + 1),
                'slug' => Str::slug(Str::limit($row->title, 80, '')).'-'.strtolower(substr($row->opportunity_id, 0, 6)),
                'editorial_status' => 'approved',
                'publication_status' => $row->is_active ? 'published' : 'not_published',
                'published_at' => $row->is_active ? now() : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['publication_status', 'deadline']);
            $table->dropUnique(['code']);
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'code', 'slug', 'location', 'region', 'work_mode', 'eligibility', 'audience', 'apply_method', 'apply_url', 'apply_email',
                'apply_instructions', 'attachment_path', 'attachment_name', 'editorial_status', 'publication_status', 'submitted_by',
                'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'published_by', 'published_at', 'publish_at',
                'status_reason', 'closed_at', 'archived_at',
            ]);
        });
    }
};

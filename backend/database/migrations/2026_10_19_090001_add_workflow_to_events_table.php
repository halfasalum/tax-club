<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Events and campaigns with review, audience, registration rules,
     * capacity and closure (EVT-REQ01-08).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('code', 12)->nullable()->unique()->after('event_id');
            $table->string('type', 30)->default('workshop')->after('slug');
            $table->string('mode', 20)->default('in_person')->after('type');
            $table->text('objective')->nullable()->after('description');
            $table->string('organizer', 200)->nullable()->after('objective');
            $table->string('region', 60)->nullable()->after('location');
            $table->string('online_url', 500)->nullable()->after('region');
            $table->json('audience')->nullable();
            $table->boolean('registration_required')->default(true);
            $table->timestamp('registration_closes_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('waitlist_enabled')->default(false);
            $table->string('registration_question', 300)->nullable();
            $table->boolean('is_public')->default(true);

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
            $table->uuid('closed_by')->nullable();
            $table->text('closing_notes')->nullable();

            $table->index(['publication_status', 'starts_at']);
        });

        // Events created before the workflow stay as they were: live if active.
        DB::table('events')->orderBy('created_at')->get(['event_id', 'is_active'])->each(function (object $event, int $index) {
            DB::table('events')->where('event_id', $event->event_id)->update([
                'code' => sprintf('EV-%04d', $index + 1),
                'editorial_status' => 'approved',
                'publication_status' => $event->is_active ? 'published' : 'not_published',
                'published_at' => $event->is_active ? now() : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['publication_status', 'starts_at']);
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'type', 'mode', 'objective', 'organizer', 'region', 'online_url', 'audience', 'registration_required',
                'registration_closes_at', 'capacity', 'waitlist_enabled', 'registration_question', 'is_public',
                'editorial_status', 'publication_status', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note',
                'published_by', 'published_at', 'publish_at', 'status_reason', 'closed_at', 'closed_by', 'closing_notes',
            ]);
        });
    }
};

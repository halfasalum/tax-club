<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Targeted and broadcast notifications (NOT-REQ03) and a record of
     * automatic reminders so none is sent twice (NOT-REQ04).
     */
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->uuid('broadcast_id')->primary();
            $table->string('code', 12)->unique();
            $table->string('title', 200);
            $table->text('message_sw');
            $table->text('message_en')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->boolean('send_sms')->default(false);
            $table->boolean('send_inbox')->default(true);
            $table->json('audience')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('send_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at']);
        });

        Schema::create('reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40);
            $table->string('subject_id', 40);
            $table->uuid('user_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['kind', 'subject_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders_sent');
        Schema::dropIfExists('broadcasts');
    }
};

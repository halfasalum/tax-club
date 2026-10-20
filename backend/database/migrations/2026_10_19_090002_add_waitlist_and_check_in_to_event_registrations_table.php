<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waitlist, registration answers and check-in records (EVT-REQ04-06).
     */
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('status', 20)->default('registered')->change();
            $table->text('answer')->nullable();
            $table->timestamp('waitlisted_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->uuid('checked_in_by')->nullable();
            $table->string('check_in_method', 20)->nullable();
            $table->boolean('walk_in')->default(false);
            $table->text('correction_reason')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'status']);
            $table->dropColumn(['answer', 'waitlisted_at', 'promoted_at', 'cancelled_at', 'checked_in_at', 'checked_in_by', 'check_in_method', 'walk_in', 'correction_reason', 'updated_at']);
        });
    }
};

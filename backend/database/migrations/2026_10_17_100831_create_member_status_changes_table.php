<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membership lifecycle history shown to officers and members (MEM-REQ04):
     * status changes (approved, suspended, reactivated...) and category
     * changes (e.g. student to alumni, REG-REQ07).
     */
    public function up(): void
    {
        Schema::create('member_status_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('field', 20)->default('account_status');
            $table->string('from_value', 30)->nullable();
            $table->string('to_value', 30);
            $table->text('reason')->nullable();
            $table->uuid('changed_by')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'changed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            $table->timestamp('alumni_requested_at')->nullable()->after('membership_issued_at');
            $table->timestamp('alumni_since')->nullable()->after('alumni_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status_changed_at', 'alumni_requested_at', 'alumni_since']);
        });

        Schema::dropIfExists('member_status_changes');
    }
};

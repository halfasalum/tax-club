<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the lists and counts used on every page: members by
     * status and institution, unread notifications, progress and attempts.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['account_status', 'user_type']);
            $table->index(['institution_id', 'account_status']);
            $table->index('total_points');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_id', 'read_at']);
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->index(['user_id', 'quiz_id', 'status']);
            $table->index(['user_id', 'is_best_attempt']);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_best_attempt']);
            $table->dropIndex(['user_id', 'quiz_id', 'status']);
        });
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex(['notifiable_id', 'read_at']));
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['total_points']);
            $table->dropIndex(['institution_id', 'account_status']);
            $table->dropIndex(['account_status', 'user_type']);
        });
    }
};

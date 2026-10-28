<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reward Redemptions — a member's request to redeem a reward
     * with points. Points are deducted at request time; status tracks
     * the fulfillment lifecycle (pending -> approved/fulfilled, or
     * rejected -> points refunded).
     */
    public function up(): void
    {
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->uuid('redemption_id')->primary();
            $table->uuid('user_id');
            $table->uuid('reward_id');
            $table->integer('points_spent');
            $table->enum('status', ['pending', 'approved', 'fulfilled', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->uuid('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('reward_id')
                ->references('reward_id')
                ->on('rewards')
                ->restrictOnDelete();

            $table->foreign('processed_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
    }
};

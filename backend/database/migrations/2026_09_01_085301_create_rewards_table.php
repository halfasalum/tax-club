<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rewards catalog — items members can redeem with points earned
     * from quizzes and content progress (airtime, certificates,
     * merchandise, recognition).
     */
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->uuid('reward_id')->primary();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('type', ['airtime', 'certificate', 'merchandise', 'recognition']);
            $table->integer('points_cost');
            $table->integer('stock')->nullable(); // null = unlimited
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};

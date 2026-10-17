<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Points ledger and rules (GAM-REQ08), badges (GAM-REQ09), settings for
     * leaderboards (GAM-REQ10) and notification preferences (NOT-REQ05).
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gamification_rules', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->integer('points');
            $table->boolean('is_active')->default(true);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('points_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->integer('points');
            $table->string('reason', 40);
            $table->string('source_type', 30)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('group_key', 80)->nullable();
            $table->string('description', 255)->nullable();
            $table->uuid('awarded_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
            $table->index(['created_at', 'user_id']);
            $table->index(['user_id', 'group_key']);
            $table->index(['reason', 'source_type', 'source_id']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('icon', 16)->default('🏅');
            $table->string('name_sw', 120);
            $table->string('name_en', 120);
            $table->string('description_sw', 255)->nullable();
            $table->string('description_en', 255)->nullable();
            $table->string('criteria', 30);
            $table->unsignedInteger('threshold')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->foreignId('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->timestamp('awarded_at');

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'badge_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('category', 30);
            $table->boolean('sms')->default(true);
            $table->boolean('inbox')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'category']);
        });

        // Points earned in the prototype become an opening balance.
        DB::table('users')->where('total_points', '>', 0)->orderBy('user_id')->each(function (object $user) {
            DB::table('points_ledger')->insert([
                'user_id' => $user->user_id,
                'points' => $user->total_points,
                'reason' => 'opening_balance',
                'description' => 'Points from the prototype',
                'created_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('points_ledger');
        Schema::dropIfExists('gamification_rules');
        Schema::dropIfExists('app_settings');
    }
};

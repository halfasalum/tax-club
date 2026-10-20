<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved opportunities (OPP-REQ06) and engagement records for
     * reporting (OPP-REQ08).
     */
    public function up(): void
    {
        Schema::create('opportunity_saves', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('opportunity_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'opportunity_id']);
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('opportunity_id')->references('opportunity_id')->on('opportunities')->cascadeOnDelete();
        });

        Schema::create('opportunity_engagements', function (Blueprint $table) {
            $table->id();
            $table->uuid('opportunity_id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 20);
            $table->string('user_type', 20)->nullable();
            $table->string('region', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('opportunity_id')->references('opportunity_id')->on('opportunities')->cascadeOnDelete();
            $table->index(['opportunity_id', 'action']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_engagements');
        Schema::dropIfExists('opportunity_saves');
    }
};

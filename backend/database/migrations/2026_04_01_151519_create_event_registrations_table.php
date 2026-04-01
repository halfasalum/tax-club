<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Join table for the M:N relationship between users and events.
     */
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('event_id');
            $table->enum('status', ['registered', 'attended', 'cancelled'])->default('registered');
            $table->timestamp('registered_at')->useCurrent();

            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->cascadeOnDelete();

            $table->foreign('event_id')
                  ->references('event_id')
                  ->on('events')
                  ->cascadeOnDelete();

            $table->unique(['user_id', 'event_id']); // prevent duplicate registrations
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
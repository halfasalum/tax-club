<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TRA coordinators and the regions they look after (INS-REQ06). Ended
     * assignments are kept for history.
     */
    public function up(): void
    {
        Schema::create('coordinator_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('region', 60);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('end_reason')->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->uuid('ended_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['region', 'ends_on']);
            $table->index(['user_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinator_assignments');
    }
};

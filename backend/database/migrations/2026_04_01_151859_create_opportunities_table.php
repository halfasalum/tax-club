<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table renamed to 'opportunities' to avoid conflict with
     * Laravel's built-in queue 'jobs' table.
     * Covers job listings, internships, and volunteer opportunities.
     */
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->uuid('opportunity_id')->primary();
            $table->string('title', 200);
            $table->string('organization', 200);
            $table->enum('type', ['job', 'internship', 'volunteer']);
            $table->text('description')->nullable();
            $table->date('deadline')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('posted_by')
                  ->references('user_id')
                  ->on('users')
                  ->nullOnDelete();

            // Composite index for active listings queries
            $table->index(['is_active', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
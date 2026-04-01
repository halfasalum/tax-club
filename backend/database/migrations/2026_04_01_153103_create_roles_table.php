<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles table.
     * - Roles are created by admin (not hardcoded).
     * - is_default: if true, this role is auto-assigned to every new registered user.
     * - Only one role should be marked as default at a time (enforced at app level).
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('role_id')->primary();
            $table->string('name', 100)->unique();        // e.g. "Student", "Educator"
            $table->string('slug', 100)->unique();        // e.g. "student", "educator"
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false); // auto-assign to new registrations
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();        // admin who created the role
            $table->timestamps();

            $table->foreign('created_by')
                  ->references('user_id')
                  ->on('users')
                  ->nullOnDelete();

            $table->index('is_default');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
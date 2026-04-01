<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User Roles — pivot table linking users to roles.
     *
     * A user can have many roles (M:N).
     * When a new user registers, the role with is_default = true
     * is automatically inserted here at the application level.
     *
     * assigned_by : the admin who assigned the role (null = system auto-assign on registration).
     */
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->uuid('assigned_by')->nullable();  // null = auto-assigned on registration
            $table->timestamp('assigned_at')->useCurrent();

            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->cascadeOnDelete();

            $table->foreign('role_id')
                  ->references('role_id')
                  ->on('roles')
                  ->cascadeOnDelete();

            $table->foreign('assigned_by')
                  ->references('user_id')
                  ->on('users')
                  ->nullOnDelete();

            // A user can only have the same role once
            $table->unique(['user_id', 'role_id']);

            $table->index('user_id');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
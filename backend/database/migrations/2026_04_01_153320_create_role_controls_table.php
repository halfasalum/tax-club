<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Role Controls — pivot table linking roles to module controls.
     *
     * This is where permissions are granted:
     * "Role: Educator  →  Control: upload_content  ✓"
     * "Role: Student   →  Control: view_content    ✓"
     *
     * A role can have many controls.
     * A control can be assigned to many roles.
     *
     * granted_by : the admin who assigned this control to the role (audit trail).
     */
    public function up(): void
    {
        Schema::create('role_controls', function (Blueprint $table) {
            $table->id();
            $table->uuid('role_id');
            $table->uuid('control_id');
            $table->uuid('granted_by')->nullable();   // admin who made the assignment
            $table->timestamps();

            $table->foreign('role_id')
                  ->references('role_id')
                  ->on('roles')
                  ->cascadeOnDelete();

            $table->foreign('control_id')
                  ->references('control_id')
                  ->on('module_controls')
                  ->cascadeOnDelete();

            $table->foreign('granted_by')
                  ->references('user_id')
                  ->on('users')
                  ->nullOnDelete();

            // Prevent duplicate assignments
            $table->unique(['role_id', 'control_id']);

            $table->index('role_id');
            $table->index('control_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_controls');
    }
};
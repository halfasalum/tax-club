<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module Controls table.
     * Each control is a specific action within a module.
     * Examples:
     *   Module: Users    → Controls: add_user, edit_user, delete_user, view_users
     *   Module: Content  → Controls: upload_content, edit_content, delete_content
     *   Module: Quizzes  → Controls: create_quiz, grade_quiz, view_results
     *
     * - name  : human-readable label, e.g. "Add User"
     * - slug  : machine key used in policy checks, e.g. "add_user"
     * - module_id : the module this control belongs to
     * - is_active : allows disabling a control without deleting it
     */
    public function up(): void
    {
        Schema::create('module_controls', function (Blueprint $table) {
            $table->uuid('control_id')->primary();
            $table->string('name', 150);             // e.g. "Add User"
            $table->string('slug', 150)->unique();   // e.g. "add_user" — used in gates/policies
            $table->uuid('module_id');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('module_id')
                  ->references('module_id')
                  ->on('modules')
                  ->cascadeOnDelete();

            $table->index('module_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_controls');
    }
};
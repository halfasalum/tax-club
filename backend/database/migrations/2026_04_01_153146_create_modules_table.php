<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modules table.
     * Represents functional areas of the app, e.g. Users, Content, Quizzes,
     * Opportunities, Events, Forum, Reports.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('module_id')->primary();
            $table->string('name', 150)->unique();   // e.g. "Users"
            $table->string('slug', 150)->unique();   // e.g. "users"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
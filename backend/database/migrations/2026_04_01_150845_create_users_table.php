<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('full_name', 150);
            $table->string('email', 150)->unique();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('password_hash', 255);
            $table->enum('user_type', ['secondary', 'college', 'university', 'alumni']);
            $table->uuid('institution_id')->nullable();
            $table->string('membership_id', 20)->unique()->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_login')->nullable();

            $table->foreign('institution_id')
                  ->references('institution_id')
                  ->on('institutions')
                  ->nullOnDelete();

            // Indexes
            $table->index('institution_id');
            $table->index('membership_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content', function (Blueprint $table) {
            $table->uuid('content_id')->primary();
            $table->string('title', 300);
            $table->enum('content_type', ['video', 'article', 'infographic', 'quiz']);
            $table->text('file_url')->nullable();
            $table->text('body_text')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreign('uploaded_by')
                  ->references('user_id')
                  ->on('users')
                  ->nullOnDelete();

            // Composite index for filtered content queries
            $table->index(['content_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content');
    }
};
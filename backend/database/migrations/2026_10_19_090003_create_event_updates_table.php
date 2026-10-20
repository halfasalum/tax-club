<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Updates, instructions and materials for participants (EVT-REQ07).
     */
    public function up(): void
    {
        Schema::create('event_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('kind', 20)->default('update');
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('file_path', 300)->nullable();
            $table->string('file_name', 200)->nullable();
            $table->boolean('registered_only')->default(false);
            $table->uuid('posted_by')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('event_id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_updates');
    }
};

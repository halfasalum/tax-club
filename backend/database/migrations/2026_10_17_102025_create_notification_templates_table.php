<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Administrator settings per notification (NOT-REQ01): which channels
     * are on, and wording that replaces the default in each language. A
     * missing row or empty text means the built-in default.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('inbox_enabled')->default(true);
            $table->text('body_sw')->nullable();
            $table->text('body_en')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};

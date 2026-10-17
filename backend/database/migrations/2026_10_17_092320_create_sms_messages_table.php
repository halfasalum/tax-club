<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every SMS the platform sends, with its delivery status and the
     * provider's reference (NOT-REQ02, NOT-REQ07).
     */
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('to', 20);
            $table->string('purpose', 60);
            $table->text('message');
            $table->string('status', 20)->default('pending');
            $table->string('provider', 20);
            $table->string('provider_reference', 100)->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'to']);
            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};

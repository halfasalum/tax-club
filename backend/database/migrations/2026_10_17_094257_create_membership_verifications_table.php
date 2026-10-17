<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every membership check (MEM-REQ03): what was checked, how, by whom and
     * the answer given. Lets TRA spot scraping and review officer lookups.
     */
    public function up(): void
    {
        Schema::create('membership_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('member_id')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->string('method', 10);
            $table->string('result', 20);
            $table->boolean('full_details')->default(false);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('member_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_verifications');
    }
};

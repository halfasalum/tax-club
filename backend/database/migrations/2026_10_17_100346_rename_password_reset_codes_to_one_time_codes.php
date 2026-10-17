<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SMS codes are now used for password resets, staff sign-in and phone
     * number changes, so the table gets a purpose and optional data.
     */
    public function up(): void
    {
        Schema::rename('password_reset_codes', 'one_time_codes');

        Schema::table('one_time_codes', function (Blueprint $table) {
            $table->string('purpose', 30)->default('password_reset')->after('user_id');
            $table->json('meta')->nullable()->after('code_hash');
            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::table('one_time_codes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'purpose']);
            $table->dropColumn(['purpose', 'meta']);
        });

        Schema::rename('one_time_codes', 'password_reset_codes');
    }
};

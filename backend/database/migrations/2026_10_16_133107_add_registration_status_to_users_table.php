<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the registration lifecycle required by REG-REQ01–03:
     * a registration reference, an account status, the verifying officer
     * and a reason for rejection. Existing verified users become active, and
     * email becomes optional because most students sign up by phone.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->change();
            $table->string('registration_reference', 30)->nullable()->unique()->after('membership_id');
            $table->string('account_status', 30)->default('pending_verification')->after('is_verified');
            $table->text('status_reason')->nullable()->after('account_status');
            $table->uuid('verified_by')->nullable()->after('status_reason');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->rememberToken();

            $table->index('account_status');
        });

        DB::table('users')->where('is_verified', true)->update(['account_status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status']);
            $table->dropUnique(['registration_reference']);
            $table->dropColumn([
                'registration_reference',
                'account_status',
                'status_reason',
                'verified_by',
                'verified_at',
                'remember_token',
            ]);
        });
    }
};

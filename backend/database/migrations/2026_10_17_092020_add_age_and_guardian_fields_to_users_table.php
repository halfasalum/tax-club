<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Age and guardian consent for REG-REQ01 and minor safeguarding
     * (PRO-REQ03, SAF-REQ05). Existing accounts keep a null age band until
     * they add a date of birth.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('gender', 10)->nullable()->after('date_of_birth');
            $table->string('age_band', 10)->nullable()->after('gender');
            $table->string('preferred_locale', 5)->default('sw')->after('age_band');
            $table->string('guardian_name', 150)->nullable()->after('preferred_locale');
            $table->string('guardian_phone', 20)->nullable()->after('guardian_name');
            $table->string('guardian_relationship', 20)->nullable()->after('guardian_phone');
            $table->timestamp('guardian_consent_at')->nullable()->after('guardian_relationship');
            $table->uuid('guardian_consent_confirmed_by')->nullable()->after('guardian_consent_at');
            $table->timestamp('guardian_consent_confirmed_at')->nullable()->after('guardian_consent_confirmed_by');

            $table->index('age_band');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['age_band']);
            $table->dropColumn([
                'date_of_birth', 'gender', 'age_band', 'preferred_locale',
                'guardian_name', 'guardian_phone', 'guardian_relationship', 'guardian_consent_at',
                'guardian_consent_confirmed_by', 'guardian_consent_confirmed_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The card token is what the QR code carries (MEM-REQ02/03). It is random,
     * so a card cannot be forged from a membership number, and it is replaced
     * when a card is reissued so old printouts stop verifying.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('card_token', 40)->nullable()->unique()->after('membership_id');
            $table->timestamp('membership_issued_at')->nullable()->after('card_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['card_token']);
            $table->dropColumn(['card_token', 'membership_issued_at']);
        });
    }
};

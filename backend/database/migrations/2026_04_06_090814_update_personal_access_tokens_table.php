<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alter personal_access_tokens table only.
     *
     * Changes tokenable_id from bigint unsigned → char(36)
     * and tokenable_type index to match uuidMorphs structure.
     *
     * This is needed because the User model uses UUID primary keys
     * and the default Sanctum morphs() column cannot store UUID strings.
     *
     * Run with:
     *   php artisan migrate
     */
    public function up(): void
    {
        // ── 1. Drop the existing morph index ──────────────────────
        // Laravel names the index as {table}_{column}_type_{column}_id_index
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');
        });

        // ── 2. Alter the tokenable_id column to char(36) ──────────
        // We use a raw statement because Blueprint::change() on morph
        // columns can behave inconsistently across DB drivers.
        DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_id CHAR(36) NOT NULL');

        // ── 3. Re-create the index to match uuidMorphs structure ──
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
        });
    }

    /**
     * Reverse — restore tokenable_id back to bigint unsigned.
     * Note: any existing UUID values will be lost on rollback.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');
        });

        DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_id BIGINT UNSIGNED NOT NULL');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
        });
    }
};
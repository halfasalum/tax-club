<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Institution registration fields from INS-REQ01–03: a unique code,
     * an Active/Inactive status with reason and history, contact details
     * and the officer who registered it. Existing institutions stay active.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('institution_id');
            $table->string('status', 20)->default('active')->after('district');
            $table->text('status_reason')->nullable()->after('status');
            $table->string('contact_name', 150)->nullable()->after('status_reason');
            $table->string('contact_phone', 20)->nullable()->after('contact_name');
            $table->string('contact_email', 150)->nullable()->after('contact_phone');
            $table->uuid('created_by')->nullable()->after('contact_email');
            $table->timestamp('updated_at')->nullable()->after('created_at');

            $table->index(['status', 'region']);
        });

        Schema::create('institution_status_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('institution_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->uuid('changed_by')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('institution_id')->references('institution_id')->on('institutions')->cascadeOnDelete();
            $table->index('institution_id');
        });

        $regionCodes = array_change_key_case(array_flip(config('tanzania.regions')), CASE_LOWER);
        $sequence = [];

        DB::table('institutions')->whereNull('code')->orderBy('created_at')->each(function (object $institution) use ($regionCodes, &$sequence) {
            $prefix = ($regionCodes[strtolower(trim((string) $institution->region))] ?? 'TZA').'-'.strtoupper(substr((string) $institution->type, 0, 1));
            $sequence[$prefix] = ($sequence[$prefix] ?? 0) + 1;

            DB::table('institutions')->where('institution_id', $institution->institution_id)->update([
                'code' => $prefix.'-'.str_pad((string) $sequence[$prefix], 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_status_history');

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropIndex(['status', 'region']);
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'status', 'status_reason', 'contact_name', 'contact_phone', 'contact_email', 'created_by', 'updated_at']);
        });
    }
};

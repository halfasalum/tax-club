<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tax Clubs under institutions (INS-REQ04) and the history of patron
     * assignments (INS-REQ05). Ending an assignment keeps the record.
     */
    public function up(): void
    {
        Schema::create('tax_clubs', function (Blueprint $table) {
            $table->uuid('tax_club_id')->primary();
            $table->uuid('institution_id');
            $table->string('name', 200);
            $table->date('established_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('status_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('institution_id')->on('institutions')->cascadeOnDelete();
            $table->index(['institution_id', 'status']);
        });

        Schema::create('club_patrons', function (Blueprint $table) {
            $table->id();
            $table->uuid('tax_club_id');
            $table->uuid('user_id');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('end_reason')->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->uuid('ended_by')->nullable();
            $table->timestamps();

            $table->foreign('tax_club_id')->references('tax_club_id')->on('tax_clubs')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['tax_club_id', 'ends_on']);
            $table->index(['user_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_patrons');
        Schema::dropIfExists('tax_clubs');
    }
};

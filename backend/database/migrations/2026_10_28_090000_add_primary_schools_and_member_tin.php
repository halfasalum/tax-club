<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Primary school pupils can join, and college and university students
     * give their TIN (Taxpayer Identification Number) when they register.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['primary', 'secondary', 'college', 'university', 'alumni', 'staff'])->change();
            $table->string('tin', 9)->nullable()->unique()->after('phone');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->enum('type', ['primary', 'secondary', 'college', 'university'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tin']);
            $table->dropColumn('tin');
            $table->enum('user_type', ['secondary', 'college', 'university', 'alumni', 'staff'])->change();
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->enum('type', ['secondary', 'college', 'university'])->change();
        });
    }
};

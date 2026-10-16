<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Gives learning content and events stable public URLs (slugs) for the
     * SEO website. Content is only listed publicly when `is_public` is set,
     * so member-only lessons stay behind login.
     */
    public function up(): void
    {
        Schema::table('content', function (Blueprint $table) {
            $table->string('slug', 320)->nullable()->unique()->after('title');
            $table->text('summary')->nullable()->after('slug');
            $table->boolean('is_public')->default(false)->after('is_active');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('slug', 320)->nullable()->unique()->after('title');
        });

        foreach (['content' => 'content_id', 'events' => 'event_id'] as $table => $key) {
            DB::table($table)->whereNull('slug')->orderBy($key)->each(function (object $row) use ($table, $key) {
                DB::table($table)->where($key, $row->{$key})->update([
                    'slug' => Str::slug(Str::limit($row->title, 80, '')).'-'.Str::lower(Str::substr($row->{$key}, 0, 8)),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });

        Schema::table('content', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'summary', 'is_public']);
        });
    }
};

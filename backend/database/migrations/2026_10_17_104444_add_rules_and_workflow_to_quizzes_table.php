<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quiz rules and review before publication (GAM-REQ01-04, GAM-REQ07).
     */
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('quiz_id');
            $table->foreignId('category_id')->nullable()->after('content_id')->constrained('content_categories')->nullOnDelete();
            $table->text('instructions')->nullable()->after('description');
            $table->json('audience')->nullable()->after('instructions');
            $table->string('mode', 10)->default('scored')->after('audience');
            $table->unsignedTinyInteger('pass_mark')->default(50)->after('mode');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable()->after('pass_mark');
            $table->unsignedTinyInteger('max_attempts')->nullable()->after('time_limit_minutes');
            $table->string('retained_result', 10)->default('best')->after('max_attempts');
            $table->string('show_answers', 20)->default('after_submit')->after('retained_result');
            $table->boolean('shuffle_questions')->default(false)->after('show_answers');
            $table->string('editorial_status', 20)->default('draft')->after('is_active');
            $table->string('publication_status', 20)->default('not_published')->after('editorial_status');
            $table->timestamp('opens_at')->nullable()->after('publication_status');
            $table->timestamp('closes_at')->nullable()->after('opens_at');
            $table->uuid('created_by')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->index(['publication_status', 'opens_at']);
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->text('explanation')->nullable()->after('question_text');
        });

        // Prototype quizzes: "multiple_choice" means one correct answer.
        DB::table('quiz_questions')->where('question_type', 'multiple_choice')->update(['question_type' => 'single_choice']);

        $sequence = 0;
        DB::table('quizzes')->orderBy('created_at')->orderBy('quiz_id')->each(function (object $quiz) use (&$sequence) {
            $sequence++;
            DB::table('quizzes')->where('quiz_id', $quiz->quiz_id)->update([
                'code' => sprintf('QZ-%04d', $sequence),
                'editorial_status' => $quiz->is_active ? 'approved' : 'draft',
                'publication_status' => $quiz->is_active ? 'published' : 'not_published',
                'published_at' => $quiz->is_active ? ($quiz->created_at ?? now()) : null,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('quiz_questions')->where('question_type', 'single_choice')->update(['question_type' => 'multiple_choice']);

        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn('explanation');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropIndex(['publication_status', 'opens_at']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'instructions', 'audience', 'mode', 'pass_mark', 'time_limit_minutes', 'max_attempts', 'retained_result',
                'show_answers', 'shuffle_questions', 'editorial_status', 'publication_status', 'opens_at', 'closes_at',
                'created_by', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'published_by', 'published_at',
            ]);
        });
    }
};

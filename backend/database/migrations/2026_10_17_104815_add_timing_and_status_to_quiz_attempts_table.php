<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side quiz attempts (GAM-REQ05-07): the server records when an
     * attempt started and when it must end, answers are saved as members go,
     * and the result that counts follows the quiz's rule.
     */
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->string('status', 20)->default('submitted')->after('quiz_id');
            $table->unsignedSmallInteger('attempt_number')->default(1)->after('status');
            $table->json('question_order')->nullable()->after('attempt_number');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->decimal('percent', 5, 2)->default(0);
            $table->boolean('passed')->default(false);
            $table->index(['quiz_id', 'status']);
        });

        Schema::table('quiz_user_responses', function (Blueprint $table) {
            $table->json('selected_answer_ids')->nullable()->after('answer_id');
            $table->index(['attempt_id', 'question_id']);
        });

        DB::table('quiz_attempts')->orderBy('attempted_at')->each(function (object $attempt) {
            $percent = $attempt->max_score > 0 ? round($attempt->score / $attempt->max_score * 100, 2) : 0;

            DB::table('quiz_attempts')->where('attempt_id', $attempt->attempt_id)->update([
                'status' => 'submitted',
                'started_at' => $attempt->attempted_at,
                'submitted_at' => $attempt->attempted_at,
                'percent' => $percent,
                'passed' => $percent >= 50,
                'attempt_number' => DB::table('quiz_attempts')->where('user_id', $attempt->user_id)->where('quiz_id', $attempt->quiz_id)->where('attempted_at', '<=', $attempt->attempted_at)->count(),
            ]);
        });

        DB::table('quiz_user_responses')->whereNotNull('answer_id')->update(['selected_answer_ids' => DB::raw("'[]'")]);
        DB::table('quiz_user_responses')->whereNotNull('answer_id')->orderBy('response_id')->each(function (object $response) {
            DB::table('quiz_user_responses')->where('response_id', $response->response_id)->update(['selected_answer_ids' => json_encode([$response->answer_id])]);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_user_responses', function (Blueprint $table) {
            $table->dropIndex(['attempt_id', 'question_id']);
            $table->dropColumn('selected_answer_ids');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex(['quiz_id', 'status']);
            $table->dropColumn(['status', 'attempt_number', 'question_order', 'started_at', 'expires_at', 'submitted_at', 'auto_submitted', 'percent', 'passed']);
        });
    }
};

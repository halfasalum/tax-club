<?php

namespace Database\Seeders;

use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\ContentVersion;
use App\Models\LearningModule;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\ContentWorkflow;
use App\Support\HtmlSanitizer;
use App\Support\QuizWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starter lessons, modules and quizzes in Kiswahili and English, written
 * from www.tra.go.tz (database/seeders/data/tra-lessons.json). Every
 * lesson ends with links to the TRA pages it is based on.
 *
 * Safe to re-run: items that already exist (by slug or code) are skipped,
 * so officers' later edits are kept. Content officers should still review
 * figures each year, as for any tax content (decision D-10).
 */
class TraLearningSeeder extends Seeder
{
    private const DATA = __DIR__.'/data/tra-lessons.json';

    /** Lessons per module, in order. */
    private const MODULES = [
        'tax-basics' => [
            'sw' => ['Misingi ya Kodi kutoka TRA', 'Jifunze TRA ni nini, haki na wajibu wa mlipakodi, TIN, VAT, kodi ya mapato, risiti za EFD na forodha.'],
            'en' => ['Tax basics from TRA', 'Learn what TRA does, taxpayer rights and obligations, TIN, VAT, income tax, EFD receipts and customs.'],
            'category' => 'taxes-and-you',
            'lessons' => ['what-is-tra', 'taxpayer-rights-obligations', 'tin-registration', 'vat-basics', 'income-tax-paye', 'efd-receipts', 'customs-import-basics'],
        ],
        'ready-for-work' => [
            'sw' => ['Jiandae kwa Ajira', 'Ajira katika kodi, kuandika CV, kujiandaa kwa usaili na mafunzo kwa vitendo.'],
            'en' => ['Get ready for work', 'Careers in tax, writing a CV, preparing for interviews and internships.'],
            'category' => 'tax-careers',
            'lessons' => ['careers-at-tra-ita', 'career-writing-a-cv', 'career-interview-preparation', 'career-internships-attachments'],
        ],
    ];

    public function run(): void
    {
        $this->call(ContentCategorySeeder::class);

        $author = User::where('email', env('SUPERADMIN_EMAIL', 'superadmin@taxclub.go.tz'))->first()
            ?? User::where('user_type', 'staff')->first();

        if (! $author) {
            $this->command?->warn('No staff account found; run SuperUserSeeder first.');

            return;
        }

        $data = json_decode((string) file_get_contents(self::DATA), true, flags: JSON_THROW_ON_ERROR);
        $categories = ContentCategory::pluck('id', 'slug');
        $lessons = [];

        DB::transaction(function () use ($data, $categories, $author, &$lessons) {
            foreach ($data['lessons'] as $entry) {
                foreach (['sw', 'en'] as $language) {
                    $lesson = $this->lesson($entry, $language, $categories[$entry['category']] ?? null, $author);
                    $lessons[$entry['key']][$language] = $lesson;

                    if ($entry['quiz'] ?? null) {
                        $this->quiz($entry, $language, $lesson, $author);
                    }
                }
            }

            foreach (self::MODULES as $key => $module) {
                foreach (['sw', 'en'] as $language) {
                    $this->module($key, $module, $language, $lessons, $categories[$module['category']] ?? null, $author);
                }
            }
        });

        $this->command?->info('✅ TRA lessons, modules and quizzes seeded.');
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function lesson(array $entry, string $language, ?int $categoryId, User $author): Content
    {
        $slug = $entry['key'].'-'.$language;

        if ($existing = Content::where('slug', $slug)->first()) {
            return $existing;
        }

        $text = $entry[$language];
        $fields = [
            'title' => $text['title'],
            'summary' => $text['summary'],
            'content_type' => 'article',
            'body_markdown' => $text['body_markdown'],
            'body_text' => HtmlSanitizer::clean(Str::markdown($text['body_markdown'], ['html_input' => 'strip', 'allow_unsafe_links' => false])),
            'objectives' => implode("\n", $text['objectives']),
            'category_id' => $categoryId,
            'level' => $entry['level'],
            'language' => $language,
            'keywords' => $text['keywords'],
            'audience' => null,
            'estimated_minutes' => $entry['estimated_minutes'],
            'is_public' => true,
        ];

        $content = ContentWorkflow::create($fields, $author);
        $content->forceFill(['slug' => $slug])->save();

        // Imported as approved by the programme team; the history says so.
        $content->versions()->update(['status' => ContentVersion::APPROVED, 'reviewed_by' => $author->user_id, 'reviewed_at' => now()]);
        ContentWorkflow::event($content, 1, 'approved', __('Imported from the TRA starter content pack (www.tra.go.tz).'), $author);
        ContentWorkflow::publish($content->refresh(), $author);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function quiz(array $entry, string $language, Content $lesson, User $author): void
    {
        $definition = $entry['quiz'][$language];

        if (Quiz::where('content_id', $lesson->content_id)->where('title', $definition['title'])->exists()) {
            return;
        }

        $quiz = QuizWorkflow::create([
            'content_id' => $lesson->content_id,
            'category_id' => $lesson->category_id,
            'title' => $definition['title'],
            'description' => $definition['description'] ?? null,
            'instructions' => $language === 'sw'
                ? 'Soma somo kwanza. Una dakika 10 na majaribio 3; alama bora ndiyo inahesabiwa.'
                : 'Read the lesson first. You have 10 minutes and 3 attempts; your best score counts.',
            'language' => $language,
            'mode' => Quiz::SCORED,
            'pass_mark' => 60,
            'time_limit_minutes' => 10,
            'max_attempts' => 3,
            'retained_result' => Quiz::RETAIN_BEST,
            'show_answers' => Quiz::ANSWERS_AFTER_SUBMIT,
            'shuffle_questions' => false,
        ], $author);

        foreach ($definition['questions'] as $index => $item) {
            $question = QuizQuestion::create([
                'quiz_id' => $quiz->quiz_id,
                'question_text' => $item['question_text'],
                'explanation' => $item['explanation'] ?? null,
                'question_type' => $item['question_type'],
                'points' => 1,
                'order_index' => $index,
                'is_active' => true,
            ]);

            foreach ($item['answers'] as $position => $answer) {
                QuizAnswer::create([
                    'question_id' => $question->question_id,
                    'answer_text' => $answer['answer_text'],
                    'is_correct' => (bool) $answer['is_correct'],
                    'order_index' => $position,
                ]);
            }
        }

        $quiz->forceFill([
            'editorial_status' => Quiz::APPROVED,
            'reviewed_by' => $author->user_id,
            'reviewed_at' => now(),
            'total_marks' => count($definition['questions']),
        ])->save();
        QuizWorkflow::event($quiz, 'approved', __('Imported from the TRA starter content pack (www.tra.go.tz).'), $author);
        QuizWorkflow::publish($quiz, $author);
    }

    /**
     * @param  array<string, mixed>  $module
     * @param  array<string, array<string, Content>>  $lessons
     */
    private function module(string $key, array $module, string $language, array $lessons, ?int $categoryId, User $author): void
    {
        $slug = $key.'-'.$language;

        if (LearningModule::where('slug', $slug)->exists()) {
            return;
        }

        [$title, $summary] = $module[$language];
        $record = LearningModule::create([
            'slug' => $slug,
            'title' => $title,
            'summary' => $summary,
            'category_id' => $categoryId,
            'level' => 'beginner',
            'language' => $language,
            'status' => LearningModule::PUBLISHED,
            'sort_order' => array_search($key, array_keys(self::MODULES), true),
            'created_by' => $author->user_id,
            'published_at' => now(),
        ]);

        $position = 0;

        foreach ($module['lessons'] as $lessonKey) {
            if ($lesson = $lessons[$lessonKey][$language] ?? null) {
                $record->lessons()->attach($lesson->content_id, ['position' => $position++, 'is_required' => true]);
            }
        }
    }
}

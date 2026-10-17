<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeSeeder extends Seeder
{
    /**
     * Starter badges (GAM-REQ09). Safe to re-run; edits made by
     * administrators to existing badges are kept.
     */
    public function run(): void
    {
        $badges = [
            ['first-lesson', '📘', 'Somo la Kwanza', 'First lesson', 'Kamilisha somo lako la kwanza.', 'Complete your first lesson.', 'lessons_completed', 1],
            ['bookworm', '📚', 'Msomaji Hodari', 'Bookworm', 'Kamilisha masomo 10.', 'Complete 10 lessons.', 'lessons_completed', 10],
            ['module-finisher', '🧩', 'Mmalizaji wa Moduli', 'Module finisher', 'Kamilisha moduli nzima.', 'Complete a whole module.', 'modules_completed', 1],
            ['first-pass', '✅', 'Umefaulu', 'First pass', 'Faulu jaribio lako la kwanza.', 'Pass your first quiz.', 'quizzes_passed', 1],
            ['quiz-champion', '🏆', 'Bingwa wa Maswali', 'Quiz champion', 'Faulu majaribio 10 tofauti.', 'Pass 10 different quizzes.', 'quizzes_passed', 10],
            ['perfect-score', '💯', 'Alama Kamili', 'Perfect score', 'Pata 100% kwenye jaribio.', 'Score 100% in a quiz.', 'perfect_scores', 1],
            ['century', '⭐', 'Pointi 100', '100 points', 'Fikisha pointi 100.', 'Reach 100 points.', 'points_total', 100],
            ['tax-star', '🌟', 'Nyota wa Kodi', 'Tax star', 'Fikisha pointi 500.', 'Reach 500 points.', 'points_total', 500],
        ];

        foreach ($badges as $order => [$key, $icon, $nameSw, $nameEn, $descSw, $descEn, $criteria, $threshold]) {
            if (DB::table('badges')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('badges')->insert([
                'key' => $key, 'icon' => $icon, 'name_sw' => $nameSw, 'name_en' => $nameEn,
                'description_sw' => $descSw, 'description_en' => $descEn, 'criteria' => $criteria,
                'threshold' => $threshold, 'is_active' => true, 'sort_order' => $order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}

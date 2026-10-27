<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Badge;
use App\Support\AskTra;
use App\Support\Certificates;
use App\Support\Community;
use App\Support\EventLabels;
use App\Support\FeedbackDesk;
use App\Support\Leaderboard;
use App\Support\Locales;
use App\Support\MentorshipDesk;
use App\Support\NotificationPreferences;
use App\Support\OpportunityBoard;
use App\Support\Rewards;
use App\Support\SafetyDesk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Settings and labels for the mobile app, in the language it asked for.
 */
class AppController extends BaseController
{
    /**
     * GET /api/v1/app/config — public.
     */
    public function config(): JsonResponse
    {
        $mobile = config('tax_club.mobile');

        return $this->success([
            'min_version' => $mobile['min_version'],
            'latest_version' => $mobile['latest_version'],
            'store_urls' => ['android' => $mobile['android_url'], 'ios' => $mobile['ios_url']],
            'links' => [
                'website' => Locales::route('home'),
                'about' => Locales::route('about'),
                'answers' => Locales::route('answers.index'),
                'verify_certificate' => Locales::route('certificates.verify'),
            ],
            'leaderboard_enabled' => Leaderboard::enabled(),
            'labels' => [
                'user_types' => [
                    'secondary' => __('Secondary school'),
                    'college' => __('College'),
                    'university' => __('University'),
                ],
                'guardian_relationships' => [
                    'parent' => __('Parent'),
                    'guardian' => __('Guardian'),
                    'other' => __('Other'),
                ],
                'regions' => array_values(config('tanzania.regions')),
                'event_types' => EventLabels::types(),
                'event_modes' => EventLabels::modes(),
                'registration_statuses' => EventLabels::registrationStatuses(),
                'opportunity_types' => OpportunityBoard::typeLabels(),
                'feedback_categories' => FeedbackDesk::categoryLabels(),
                'feedback_statuses' => FeedbackDesk::statusLabels(),
                'report_categories' => SafetyDesk::categoryLabels(),
                'question_statuses' => AskTra::statusLabels(),
                'space_types' => Community::typeLabels(),
                'mentor_areas' => MentorshipDesk::areaLabels(),
                'session_modes' => [
                    'in_person' => __('In person'),
                    'phone' => __('Phone call'),
                    'video' => __('Video call'),
                    'at_club' => __('At the Tax Club'),
                ],
                'certificate_kinds' => Certificates::kindLabels(),
                'reward_statuses' => Rewards::statusLabels(),
            ],
        ]);
    }

    /**
     * GET /api/v1/notification-preferences
     */
    public function preferences(Request $request): JsonResponse
    {
        $saved = NotificationPreferences::all($request->user());

        return $this->success(collect(NotificationPreferences::categories())->map(fn (array $category, string $key) => [
            'category' => $key,
            'label' => $category['label'],
            'description' => $category['description'],
            ...$saved[$key],
        ])->values());
    }

    /**
     * PUT /api/v1/notification-preferences {category, sms, inbox}
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => 'required|in:'.implode(',', array_keys(NotificationPreferences::categories())),
            'sms' => 'required|boolean',
            'inbox' => 'required|boolean',
        ]);

        NotificationPreferences::set($request->user(), $data['category'], $data['sms'], $data['inbox']);

        return $this->preferences($request);
    }

    /**
     * GET /api/v1/badges — every badge, with the ones the member earned.
     */
    public function badges(Request $request): JsonResponse
    {
        $earned = DB::table('user_badges')->where('user_id', $request->user()->user_id)->pluck('awarded_at', 'badge_id');

        return $this->success(Badge::where('is_active', true)->orderBy('sort_order')->orderBy('threshold')->get()->map(fn ($badge) => [
            'key' => $badge->key,
            'icon' => $badge->icon,
            'name' => $badge->name(),
            'description' => $badge->description(),
            'earned' => $earned->has($badge->id),
            'earned_at' => $earned->get($badge->id),
        ]));
    }
}

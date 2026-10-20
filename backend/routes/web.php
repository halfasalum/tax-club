<?php

use App\Http\Controllers\Web\AnswersController;
use App\Http\Controllers\Web\CertificateVerifyController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\LearnController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\SitemapController;
use App\Livewire\Admin\Alumni\Stories as AlumniStories;
use App\Livewire\Admin\AuditLog as AdminAuditLog;
use App\Livewire\Admin\Broadcasts\Form as BroadcastForm;
use App\Livewire\Admin\Broadcasts\Index as BroadcastIndex;
use App\Livewire\Admin\Broadcasts\Show as BroadcastShow;
use App\Livewire\Admin\Certificates as AdminCertificates;
use App\Livewire\Admin\Community\Members as CommunityMembers;
use App\Livewire\Admin\Community\Spaces as CommunitySpaces;
use App\Livewire\Admin\Competitions\Form as CompetitionForm;
use App\Livewire\Admin\Competitions\Index as CompetitionIndex;
use App\Livewire\Admin\Competitions\Show as CompetitionShow;
use App\Livewire\Admin\Coordinators as AdminCoordinators;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Events\CheckIn as EventCheckIn;
use App\Livewire\Admin\Events\Form as EventForm;
use App\Livewire\Admin\Events\Index as EventIndex;
use App\Livewire\Admin\Events\Show as EventShow;
use App\Livewire\Admin\Feedback\Index as FeedbackIndex;
use App\Livewire\Admin\Feedback\Show as FeedbackShow;
use App\Livewire\Admin\GamificationSettings as AdminGamification;
use App\Livewire\Admin\Institutions\Form as InstitutionForm;
use App\Livewire\Admin\Institutions\Import as InstitutionImport;
use App\Livewire\Admin\Institutions\Index as InstitutionIndex;
use App\Livewire\Admin\Institutions\Show as InstitutionShow;
use App\Livewire\Admin\Learning\Categories as LearningCategories;
use App\Livewire\Admin\Learning\Form as LearningForm;
use App\Livewire\Admin\Learning\Index as LearningIndex;
use App\Livewire\Admin\Learning\Modules as LearningModules;
use App\Livewire\Admin\Learning\Show as LearningShow;
use App\Livewire\Admin\Members\Index as MemberIndex;
use App\Livewire\Admin\Members\Show as MemberShow;
use App\Livewire\Admin\Mentorship\Index as MentorshipIndex;
use App\Livewire\Admin\Moderation\Index as ModerationIndex;
use App\Livewire\Admin\Moderation\Report as SafetyReport;
use App\Livewire\Admin\Moderation\Restrictions as ModerationRestrictions;
use App\Livewire\Admin\Moderation\Show as ModerationShow;
use App\Livewire\Admin\NotificationSettings as AdminNotificationSettings;
use App\Livewire\Admin\Opportunities\Form as OpportunityForm;
use App\Livewire\Admin\Opportunities\Index as OpportunityIndex;
use App\Livewire\Admin\Opportunities\Report as OpportunityReport;
use App\Livewire\Admin\Opportunities\Show as OpportunityShow;
use App\Livewire\Admin\Questions\Index as QuestionIndex;
use App\Livewire\Admin\Questions\Show as QuestionShow;
use App\Livewire\Admin\Quizzes\Builder as QuizBuilder;
use App\Livewire\Admin\Quizzes\Index as QuizIndex;
use App\Livewire\Admin\Quizzes\Show as QuizShow;
use App\Livewire\Admin\Registrations as AdminRegistrations;
use App\Livewire\Admin\ReminderSettings as AdminReminders;
use App\Livewire\Admin\Rewards\Index as AdminRewards;
use App\Livewire\Admin\SmsLog as AdminSmsLog;
use App\Livewire\Admin\Statistics as AdminStatistics;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Member\Achievements\Certificate as MemberCertificate;
use App\Livewire\Member\Achievements\Index as MemberAchievements;
use App\Livewire\Member\Alumni\Directory as AlumniDirectory;
use App\Livewire\Member\Alumni\Hub as AlumniHub;
use App\Livewire\Member\Alumni\ProfileEdit as AlumniProfileEdit;
use App\Livewire\Member\Alumni\Story as AlumniStory;
use App\Livewire\Member\Ask\Answer as MemberAskAnswer;
use App\Livewire\Member\Ask\Index as MemberAsk;
use App\Livewire\Member\Ask\Show as MemberAskShow;
use App\Livewire\Member\Card as MemberCard;
use App\Livewire\Member\Community\Index as MemberCommunity;
use App\Livewire\Member\Community\Post as MemberCommunityPost;
use App\Livewire\Member\Community\Space as MemberCommunitySpace;
use App\Livewire\Member\Competitions\Index as MemberCompetitions;
use App\Livewire\Member\Competitions\Show as MemberCompetition;
use App\Livewire\Member\Dashboard as MemberDashboard;
use App\Livewire\Member\Events\Index as MemberEvents;
use App\Livewire\Member\Events\Show as MemberEvent;
use App\Livewire\Member\Feedback\Index as MemberFeedback;
use App\Livewire\Member\Feedback\Show as MemberFeedbackCase;
use App\Livewire\Member\Inbox as MemberInbox;
use App\Livewire\Member\Leaderboard as MemberLeaderboard;
use App\Livewire\Member\Learn\Index as MemberLearn;
use App\Livewire\Member\Learn\Lesson as MemberLesson;
use App\Livewire\Member\Learn\Module as MemberModule;
use App\Livewire\Member\Mentorship\Index as MemberMentorship;
use App\Livewire\Member\Mentorship\Show as MentorshipShow;
use App\Livewire\Member\Opportunities\Index as MemberOpportunities;
use App\Livewire\Member\Opportunities\Show as MemberOpportunity;
use App\Livewire\Member\Profile as MemberProfilePage;
use App\Livewire\Member\Quizzes\Index as MemberQuizzes;
use App\Livewire\Member\Quizzes\Result as MemberQuizResult;
use App\Livewire\Member\Quizzes\Show as MemberQuiz;
use App\Livewire\Member\Quizzes\Take as MemberQuizTake;
use App\Livewire\Membership\Verify as VerifyMembership;
use App\Models\CompetitionEntry;
use App\Models\Event;
use App\Models\EventUpdate;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\Audit;
use App\Support\CompetitionDesk;
use App\Support\EventLabels;
use App\Support\EventManager;
use App\Support\MemberProfile;
use App\Support\MembershipStatistics;
use App\Support\OpportunityBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Public website (server-rendered, indexable)
|--------------------------------------------------------------------------
| Kiswahili at the root, English under /en. Each route is registered for
| both locales so pages can declare hreflang alternates.
*/

$publicRoutes = function (): void {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/learn', [LearnController::class, 'index'])->name('learn.index');
    Route::get('/learn/{slug}', [LearnController::class, 'show'])->name('learn.show');
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{slug}', [EventController::class, 'show'])->name('events.show');
    Route::get('/answers', [AnswersController::class, 'index'])->name('answers.index');
    Route::get('/answers/{slug}', [AnswersController::class, 'show'])->name('answers.show');
    Route::get('/certificates/verify/{token?}', [CertificateVerifyController::class, 'show'])->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:60,1')->name('certificates.verify');
};

Route::middleware('locale:sw')->name('sw.')->group($publicRoutes);
Route::middleware('locale:en')->prefix('en')->name('en.')->group($publicRoutes);

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Accounts
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
});

Route::post('/logout', function (Request $request) {
    Audit::record('auth.logout', $request->user(), after: ['channel' => 'web']);
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route(app()->getLocale().'.home');
})->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Membership card verification (MEM-REQ03): the QR code on every card opens
| /verify/{token}. Open to everyone, rate-limited and not indexed.
*/

Route::get('/verify/{token?}', VerifyMembership::class)
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware('throttle:60,1')
    ->name('membership.verify');

// Profile photos are private: the member and officers who look after them (PRO-REQ03).
Route::get('/members/{member}/photo', function (Request $request, User $member) {
    abort_unless($member->photo_path && MemberProfile::canSeePhoto($request->user(), $member), 404);

    return Storage::disk(MemberProfile::PHOTO_DISK)->response($member->photo_path, null, [
        'Cache-Control' => 'private, max-age=86400',
    ]);
})->middleware(['auth:sanctum', 'active'])->name('members.photo');

/*
|--------------------------------------------------------------------------
| Member portal and Administration Portal (not indexed)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/app', MemberDashboard::class)->name('member.dashboard');
    Route::get('/app/card', MemberCard::class)->name('member.card');
    Route::get('/app/profile', MemberProfilePage::class)->name('member.profile');
    Route::get('/app/notifications', MemberInbox::class)->name('member.notifications');
    Route::get('/app/learn', MemberLearn::class)->name('member.learn');
    Route::get('/app/quizzes', MemberQuizzes::class)->name('member.quizzes');
    Route::get('/app/points', MemberLeaderboard::class)->name('member.leaderboard');
    Route::get('/app/quizzes/attempts/{attempt}', MemberQuizTake::class)->name('member.quizzes.take');
    Route::get('/app/quizzes/attempts/{attempt}/result', MemberQuizResult::class)->name('member.quizzes.result');
    Route::get('/app/quizzes/{quiz}', MemberQuiz::class)->name('member.quizzes.show');
    Route::get('/app/learn/modules/{module:slug}', MemberModule::class)->name('member.learn.module');
    Route::get('/app/learn/{lesson:slug}', MemberLesson::class)->name('member.learn.lesson');
    Route::get('/app/events', MemberEvents::class)->name('member.events');
    Route::get('/app/events/{slug}', MemberEvent::class)->name('member.events.show');
    Route::get('/app/feedback', MemberFeedback::class)->name('member.feedback');
    Route::get('/app/community', MemberCommunity::class)->name('member.community');
    Route::get('/app/ask', MemberAsk::class)->name('member.ask');
    Route::get('/app/competitions', MemberCompetitions::class)->name('member.competitions');
    Route::get('/app/competitions/{slug}', MemberCompetition::class)->name('member.competitions.show');
    Route::get('/app/achievements', MemberAchievements::class)->name('member.achievements');
    Route::get('/app/alumni', AlumniHub::class)->name('member.alumni');
    Route::get('/app/alumni/profile', AlumniProfileEdit::class)->name('member.alumni.profile');
    Route::get('/app/alumni/directory', AlumniDirectory::class)->name('member.alumni.directory');
    Route::get('/app/alumni/stories/{slug}', AlumniStory::class)->name('member.alumni.story');
    Route::get('/app/mentorship', MemberMentorship::class)->name('member.mentorship');
    Route::get('/app/mentorship/{mentorship}', MentorshipShow::class)->name('member.mentorship.show');
    Route::get('/app/certificates/{certificate}', MemberCertificate::class)->name('member.certificates.show');

    // Competition entry files: the entrant and competition staff only.
    Route::get('/app/competitions/entries/{entry}/file', function (Request $request, CompetitionEntry $entry) {
        $user = $request->user();
        abort_unless($entry->submission_file && ($entry->user_id === $user->user_id || $user->hasControl('judge_competition') || $user->hasControl('approve_results') || $user->hasControl('create_competition')), 404);

        return Storage::disk(CompetitionDesk::SUBMISSION_DISK)->download($entry->submission_file);
    })->name('competitions.entry-file');
    Route::get('/app/ask/answers/{slug}', MemberAskAnswer::class)->name('member.ask.answer');
    Route::get('/app/ask/{reference}', MemberAskShow::class)->name('member.ask.show');
    Route::get('/app/community/posts/{post}', MemberCommunityPost::class)->name('member.community.post');
    Route::get('/app/community/{space:slug}', MemberCommunitySpace::class)->name('member.community.space');
    Route::get('/app/opportunities', MemberOpportunities::class)->name('member.opportunities');
    Route::get('/app/opportunities/{slug}', MemberOpportunity::class)->name('member.opportunities.show');

    // Counts the click, then opens the approved application channel (OPP-REQ06, OPP-REQ08).
    Route::get('/app/opportunities/{slug}/apply', function (Request $request, string $slug) {
        $opportunity = Opportunity::open()->where('slug', $slug)->firstOrFail();
        abort_unless($opportunity->isVisibleTo($request->user()) && $opportunity->applyTarget(), 404);
        OpportunityBoard::record($opportunity, $request->user(), 'apply');

        return redirect()->away($opportunity->applyTarget());
    })->middleware('throttle:30,1')->name('member.opportunities.apply');
    Route::get('/app/feedback/{reference}', MemberFeedbackCase::class)->name('member.feedback.show');

    // Event materials are private: staff, and members who may see the item (EVT-REQ07).
    Route::get('/app/events/{event}/materials/{update}', function (Request $request, Event $event, EventUpdate $update) {
        abort_unless($update->event_id === $event->event_id && $update->file_path, 404);
        $user = $request->user();
        $allowed = $user->user_type === 'staff'
            ? $user->canAccessAdmin()
            : $event->isVisibleTo($user) && EventManager::updatesFor($event, $user)->contains('id', $update->id);
        abort_unless($allowed, 404);

        return Storage::disk(EventUpdate::DISK)->download($update->file_path, $update->file_name);
    })->name('events.material');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', AdminDashboard::class)->name('dashboard');
        Route::get('/registrations', AdminRegistrations::class)
            ->middleware('permission:view_registrations')
            ->name('registrations');

        Route::get('/members', MemberIndex::class)->middleware('permission:view_members')->name('members.index');
        Route::get('/members/{member}', MemberShow::class)->middleware('permission:view_members')->name('members.show');
        Route::get('/coordinators', AdminCoordinators::class)->middleware('permission:assign_coordinator')->name('coordinators');
        Route::get('/statistics', AdminStatistics::class)->middleware('permission:view_statistics')->name('statistics');
        Route::get('/statistics/export.csv', function (Request $request) {
            $labels = MembershipStatistics::labels();
            $rows = MembershipStatistics::perInstitution($request->user()->institutionScope(), (string) $request->query('region', ''), (string) $request->query('type', ''))
                ->orderBy('institutions.region')
                ->orderBy('institutions.name');
            Audit::record('statistics.exported', after: array_filter($request->only('region', 'type')));

            return response()->streamDownload(function () use ($rows, $labels): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, [__('Code'), __('Institution'), __('Category'), __('Region'), __('District'), __('Status'), __('Active Tax Club'), ...array_values($labels)]);
                foreach ($rows->cursor() as $row) {
                    fputcsv($out, [$row->code, $row->name, $row->type, $row->region, $row->district, $row->status, $row->active_clubs > 0 ? __('Yes') : __('No'), ...array_map(fn ($column) => (int) $row->{$column}, array_keys($labels))]);
                }
                fclose($out);
            }, 'tax-club-statistics-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        })->middleware('permission:view_statistics')->name('statistics.export');
        Route::get('/notifications', AdminNotificationSettings::class)->middleware('permission:manage_notification_templates')->name('notifications');
        Route::prefix('broadcasts')->name('broadcasts.')->group(function (): void {
            Route::get('/', BroadcastIndex::class)->middleware('permission:create_broadcast,approve_broadcast')->name('index');
            Route::get('/new', BroadcastForm::class)->middleware('permission:create_broadcast')->name('create');
            Route::get('/{broadcast}', BroadcastShow::class)->middleware('permission:create_broadcast,approve_broadcast')->name('show');
            Route::get('/{broadcast}/edit', BroadcastForm::class)->middleware('permission:create_broadcast')->name('edit');
        });
        Route::get('/reminders', AdminReminders::class)->middleware('permission:manage_reminders')->name('reminders');
        Route::get('/sms', AdminSmsLog::class)->middleware('permission:view_sms_log')->name('sms');
        Route::prefix('learning')->name('learning.')->group(function (): void {
            $authors = 'permission:upload_content,edit_content,review_content,publish_content';
            Route::get('/', LearningIndex::class)->middleware($authors)->name('index');
            Route::get('/new', LearningForm::class)->middleware('permission:upload_content')->name('create');
            Route::get('/categories', LearningCategories::class)->middleware('permission:manage_content_categories')->name('categories');
            Route::get('/modules', LearningModules::class)->middleware($authors)->name('modules');
            Route::get('/{content}', LearningShow::class)->middleware($authors)->name('show');
            Route::get('/{content}/edit', LearningForm::class)->middleware('permission:upload_content,edit_content')->name('edit');
        });

        Route::prefix('quizzes')->name('quizzes.')->group(function (): void {
            $quizStaff = 'permission:create_quiz,edit_quiz,review_quiz,publish_quiz,view_results';
            Route::get('/', QuizIndex::class)->middleware($quizStaff)->name('index');
            Route::get('/new', QuizBuilder::class)->middleware('permission:create_quiz')->name('create');
            Route::get('/{quiz}', QuizShow::class)->middleware($quizStaff)->name('show');
            Route::get('/{quiz}/edit', QuizBuilder::class)->middleware('permission:create_quiz,edit_quiz')->name('edit');
        });

        Route::prefix('events')->name('events.')->group(function (): void {
            $organizers = 'permission:add_event,edit_event,review_event,publish_event,view_event_attendees,check_in_attendees';
            Route::get('/', EventIndex::class)->middleware($organizers)->name('index');
            Route::get('/new', EventForm::class)->middleware('permission:add_event')->name('create');
            Route::get('/{event}', EventShow::class)->middleware($organizers)->name('show');
            Route::get('/{event}/edit', EventForm::class)->middleware('permission:add_event,edit_event')->name('edit');
            Route::get('/{event}/check-in', EventCheckIn::class)->middleware('permission:check_in_attendees')->name('check-in');
            Route::get('/{event}/registrations.csv', function (Event $event) {
                $statuses = EventLabels::registrationStatuses();
                $methods = EventLabels::checkInMethods();
                Audit::record('event.registrations_exported', $event);

                return response()->streamDownload(function () use ($event, $statuses, $methods): void {
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, [__('Name'), __('Membership no.'), __('Category'), __('Institution'), __('Region'), __('Status'), __('Registered'), __('Checked in'), __('Check-in method'), __('Walk-in'), __('Answer')]);
                    foreach ($event->registrations()->with('user.institution')->orderBy('registered_at')->cursor() as $registration) {
                        fputcsv($out, [
                            $registration->user?->full_name, $registration->user?->membership_id, $registration->user?->user_type,
                            $registration->user?->institution?->name, $registration->user?->institution?->region,
                            $statuses[$registration->status] ?? $registration->status, $registration->registered_at?->format('Y-m-d H:i'),
                            $registration->checked_in_at?->format('Y-m-d H:i'), $methods[$registration->check_in_method] ?? '',
                            $registration->walk_in ? __('Yes') : '', $registration->answer,
                        ]);
                    }
                    fclose($out);
                }, $event->code.'-registrations.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            })->middleware('permission:view_event_attendees')->name('export');
        });

        Route::prefix('opportunities')->name('opportunities.')->group(function (): void {
            $staff = 'permission:add_opportunity,edit_opportunity,review_opportunity,publish_opportunity,view_opportunity_reports';
            Route::get('/', OpportunityIndex::class)->middleware($staff)->name('index');
            Route::get('/new', OpportunityForm::class)->middleware('permission:add_opportunity')->name('create');
            Route::get('/report', OpportunityReport::class)->middleware('permission:view_opportunity_reports')->name('report');
            Route::get('/{opportunity}', OpportunityShow::class)->middleware($staff)->name('show');
            Route::get('/{opportunity}/edit', OpportunityForm::class)->middleware('permission:add_opportunity,edit_opportunity')->name('edit');
        });

        Route::prefix('moderation')->name('moderation.')->group(function (): void {
            $moderators = 'permission:moderate_community,handle_safeguarding_concerns';
            Route::get('/', ModerationIndex::class)->middleware($moderators)->name('index');
            Route::get('/restrictions', ModerationRestrictions::class)->middleware('permission:restrict_users')->name('restrictions');
            Route::get('/report', SafetyReport::class)->middleware('permission:view_safety_reports,manage_safety_settings')->name('report');
            Route::get('/{case}', ModerationShow::class)->middleware($moderators)->name('show');
        });
        Route::prefix('competitions')->name('competitions.')->group(function (): void {
            $staff = 'permission:create_competition,review_competition,publish_competition,judge_competition,approve_results';
            Route::get('/', CompetitionIndex::class)->middleware($staff)->name('index');
            Route::get('/new', CompetitionForm::class)->middleware('permission:create_competition')->name('create');
            Route::get('/{competition}', CompetitionShow::class)->middleware($staff)->name('show');
            Route::get('/{competition}/edit', CompetitionForm::class)->middleware('permission:create_competition')->name('edit');
        });
        Route::get('/mentorship', MentorshipIndex::class)->middleware('permission:manage_mentorships,review_mentors,view_members')->name('mentorship.index');
        Route::get('/mentorship/{mentorship}', MentorshipShow::class)->middleware('permission:manage_mentorships,view_members')->name('mentorship.show');
        Route::get('/alumni/stories', AlumniStories::class)->middleware('permission:review_alumni_stories')->name('alumni.stories');
        Route::get('/rewards', AdminRewards::class)->middleware('permission:manage_rewards,award_rewards,approve_rewards')->name('rewards');
        Route::get('/certificates', AdminCertificates::class)->middleware('permission:issue_certificates')->name('certificates');
        Route::get('/questions', QuestionIndex::class)->middleware('permission:triage_questions,answer_questions,review_answers')->name('questions.index');
        Route::get('/questions/{question}', QuestionShow::class)->middleware('permission:triage_questions,answer_questions,review_answers')->name('questions.show');
        Route::get('/community', CommunitySpaces::class)->middleware('permission:manage_community_spaces')->name('community.spaces');
        Route::get('/community/{space}/members', CommunityMembers::class)->middleware('permission:manage_community_spaces')->name('community.members');

        Route::get('/feedback', FeedbackIndex::class)->middleware('permission:view_feedback')->name('feedback.index');
        Route::get('/feedback/{feedback}', FeedbackShow::class)->middleware('permission:view_feedback')->name('feedback.show');

        Route::get('/gamification', AdminGamification::class)->middleware('permission:manage_gamification')->name('gamification');
        Route::get('/audit', AdminAuditLog::class)->middleware('permission:view_audit_log')->name('audit');

        Route::prefix('institutions')->name('institutions.')->group(function (): void {
            Route::get('/', InstitutionIndex::class)->middleware('permission:view_institutions')->name('index');
            Route::get('/new', InstitutionForm::class)->middleware('permission:add_institution')->name('create');
            Route::get('/import', InstitutionImport::class)->middleware('permission:import_institutions')->name('import');
            Route::get('/import/template.csv', function () {
                $rows = [
                    ['name', 'category', 'region', 'district', 'contact_name', 'contact_phone', 'contact_email'],
                    ['Azania Secondary School', 'secondary', 'Dar es Salaam', 'Ilala', 'Head of School', '0712345678', 'azania@example.go.tz'],
                ];

                return response()->streamDownload(function () use ($rows): void {
                    $out = fopen('php://output', 'w');
                    foreach ($rows as $row) {
                        fputcsv($out, $row);
                    }
                    fclose($out);
                }, 'institutions-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            })->middleware('permission:import_institutions')->name('template');
            Route::get('/{institution}', InstitutionShow::class)->middleware('permission:view_institutions')->name('show');
            Route::get('/{institution}/edit', InstitutionForm::class)->middleware('permission:edit_institution')->name('edit');
        });
    });
});

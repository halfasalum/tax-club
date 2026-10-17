<?php

use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\LearnController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\SitemapController;
use App\Livewire\Admin\AuditLog as AdminAuditLog;
use App\Livewire\Admin\Coordinators as AdminCoordinators;
use App\Livewire\Admin\Dashboard as AdminDashboard;
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
use App\Livewire\Admin\NotificationSettings as AdminNotificationSettings;
use App\Livewire\Admin\Quizzes\Builder as QuizBuilder;
use App\Livewire\Admin\Quizzes\Index as QuizIndex;
use App\Livewire\Admin\Quizzes\Show as QuizShow;
use App\Livewire\Admin\Registrations as AdminRegistrations;
use App\Livewire\Admin\SmsLog as AdminSmsLog;
use App\Livewire\Admin\Statistics as AdminStatistics;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Member\Card as MemberCard;
use App\Livewire\Member\Dashboard as MemberDashboard;
use App\Livewire\Member\Inbox as MemberInbox;
use App\Livewire\Member\Leaderboard as MemberLeaderboard;
use App\Livewire\Member\Learn\Index as MemberLearn;
use App\Livewire\Member\Learn\Lesson as MemberLesson;
use App\Livewire\Member\Learn\Module as MemberModule;
use App\Livewire\Member\Profile as MemberProfilePage;
use App\Livewire\Member\Quizzes\Index as MemberQuizzes;
use App\Livewire\Member\Quizzes\Result as MemberQuizResult;
use App\Livewire\Member\Quizzes\Show as MemberQuiz;
use App\Livewire\Member\Quizzes\Take as MemberQuizTake;
use App\Livewire\Membership\Verify as VerifyMembership;
use App\Models\User;
use App\Support\Audit;
use App\Support\MemberProfile;
use App\Support\MembershipStatistics;
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

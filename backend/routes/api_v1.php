<?php

/**
 * ============================================================
 *  TRA Tax Club App — API V1 Routes
 *  File: routes/api_v1.php
 *
 *  Registered in bootstrap/app.php (Laravel 12) via:
 *
 *    ->withRouting(
 *        api: __DIR__.'/../routes/api_v1.php',
 *        apiPrefix: 'api/v1',
 *    )
 *
 *  Middleware:
 *    auth:sanctum     → all protected routes
 *    permission:{slug} → module control check via CheckPermission middleware
 *
 *  Registered alias in bootstrap/app.php:
 *    'permission' => \App\Http\Middleware\CheckPermission::class,
 * ============================================================
 */

use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\ContentController;
use App\Http\Controllers\API\V1\DashboardController;
use App\Http\Controllers\API\V1\EventController;
use App\Http\Controllers\API\V1\FeedbackController;
use App\Http\Controllers\API\V1\ForumPostController;
use App\Http\Controllers\API\V1\InstitutionController;
use App\Http\Controllers\API\V1\LearningController;
use App\Http\Controllers\API\V1\MembershipController;
use App\Http\Controllers\API\V1\ModuleController;
use App\Http\Controllers\API\V1\NotificationController;
use App\Http\Controllers\API\V1\OpportunityController;
use App\Http\Controllers\API\V1\ProfileController;
use App\Http\Controllers\API\V1\QuizAuthoringController;
use App\Http\Controllers\API\V1\QuizController;
use App\Http\Controllers\API\V1\RoleController;
use App\Http\Controllers\API\V1\UserController;
use App\Http\Controllers\API\V1\UserProgressController;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────
//  PUBLIC ROUTES (no auth required)
// ──────────────────────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('login/verify', [AuthController::class, 'verifyLogin'])->middleware('throttle:10,1');
    Route::post('recover', [AuthController::class, 'recover'])->middleware('throttle:10,15');
    Route::post('recover/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:10,15');
});

// Institutions list is public — needed during registration dropdown
Route::post('membership/verify', [MembershipController::class, 'verify'])->middleware('throttle:20,1');

Route::get('institutions', [InstitutionController::class, 'index']);
Route::get('institutions/{id}', [InstitutionController::class, 'show']);

// ──────────────────────────────────────────────────────────────
//  AUTHENTICATED ROUTES (auth:sanctum)
// ──────────────────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // ── Auth ──────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('verify', [AuthController::class, 'verify']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    // ── Leaderboard (members only; names are shortened for privacy) ──
    Route::get('leaderboard', [QuizController::class, 'globalLeaderboard']);

    // Profile (PRO-REQ01-03, REG-REQ07)
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('profile/photo', [ProfileController::class, 'updatePhoto'])->middleware('throttle:10,60');
    Route::delete('profile/photo', [ProfileController::class, 'deletePhoto']);
    Route::post('profile/phone', [ProfileController::class, 'requestPhoneChange'])->middleware('throttle:5,15');
    Route::post('profile/phone/confirm', [ProfileController::class, 'confirmPhoneChange'])->middleware('throttle:10,15');
    Route::post('profile/alumni-request', [ProfileController::class, 'requestAlumni']);
    Route::delete('profile/alumni-request', [ProfileController::class, 'cancelAlumniRequest']);
    Route::get('members/{id}', [ProfileController::class, 'member'])->whereUuid('id');

    // Notification inbox (NOT-REQ06)
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->whereUuid('id');

    // Digital membership card
    Route::get('membership/card', [MembershipController::class, 'card']);
    Route::post('membership/card/reissue', [MembershipController::class, 'reissue'])->middleware('throttle:5,60');

    // ── Dashboard ─────────────────────────────────────────────
    Route::get('dashboard', [DashboardController::class, 'index']);

    // ── Quizzes ───────────────────────────────────────────────
    Route::prefix('quizzes')->group(function () {
        // Taking quizzes (GAM-REQ05-07)
        Route::get('/', [QuizController::class, 'index']);
        Route::get('/my-attempts', [QuizController::class, 'myAttempts']);
        Route::put('/attempts/{attemptId}/answers', [QuizController::class, 'saveAnswer'])->whereUuid('attemptId');
        Route::post('/attempts/{attemptId}/submit', [QuizController::class, 'submitAttempt'])->whereUuid('attemptId');
        Route::get('/{id}', [QuizController::class, 'show'])->whereUuid('id');
        Route::post('/{id}/start', [QuizController::class, 'start'])->whereUuid('id');
        Route::get('/{id}/questions', [QuizController::class, 'questions'])->whereUuid('id');
        Route::get('/{id}/leaderboard', [QuizController::class, 'leaderboard'])->whereUuid('id');
        Route::post('/{id}/submit', [QuizController::class, 'submitQuiz'])->whereUuid('id');
        Route::get('/{id}/my-attempts', [QuizController::class, 'myQuizAttempts'])->whereUuid('id');
        Route::get('/{id}/attempt/{attemptId}/results', [QuizController::class, 'attemptResults']);

        // Authoring (GAM-REQ01/02); review and publishing are in the Administration Portal
        Route::middleware('permission:create_quiz,edit_quiz')->group(function () {
            Route::post('/', [QuizAuthoringController::class, 'store'])->middleware('permission:create_quiz');
            Route::put('/{id}', [QuizAuthoringController::class, 'update']);
            Route::post('/{id}/submit-for-review', [QuizAuthoringController::class, 'submit']);
            Route::patch('/{id}/toggle', [QuizAuthoringController::class, 'toggle']);
            Route::get('/{id}/manage-questions', [QuizAuthoringController::class, 'manageQuestions']);
            Route::post('/{id}/questions', [QuizAuthoringController::class, 'addQuestion']);
            Route::put('/questions/{questionId}', [QuizAuthoringController::class, 'updateQuestion']);
            Route::delete('/questions/{questionId}', [QuizAuthoringController::class, 'deleteQuestion']);
            Route::post('/questions/{questionId}/answers', [QuizAuthoringController::class, 'addAnswer']);
            Route::put('/answers/{answerId}', [QuizAuthoringController::class, 'updateAnswer']);
            Route::delete('/answers/{answerId}', [QuizAuthoringController::class, 'deleteAnswer']);
            Route::post('/reorder-questions', [QuizAuthoringController::class, 'reorderQuestions']);
        });
        Route::delete('/{id}', [QuizAuthoringController::class, 'destroy'])->middleware('permission:delete_quiz');
    });

    // ── Users ─────────────────────────────────────────────────
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:view_users');

        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:add_user');

        Route::get('/{id}', [UserController::class, 'show'])
            ->middleware('permission:view_users');

        Route::put('/{id}', [UserController::class, 'update'])
            ->middleware('permission:edit_user');

        Route::delete('/{id}', [UserController::class, 'destroy'])
            ->middleware('permission:delete_user');

        Route::post('/{id}/roles', [UserController::class, 'assignRoles'])
            ->middleware('permission:assign_role');

        Route::delete('/{id}/roles', [UserController::class, 'removeRoles'])
            ->middleware('permission:assign_role');
    });

    // ── Institutions (write — admin only) ─────────────────────
    Route::prefix('institutions')->group(function () {
        Route::post('/', [InstitutionController::class, 'store'])
            ->middleware('permission:add_institution');

        Route::put('/{id}', [InstitutionController::class, 'update'])
            ->middleware('permission:edit_institution');

        Route::delete('/{id}', [InstitutionController::class, 'destroy'])
            ->middleware('permission:delete_institution');
    });

    // ── Roles ─────────────────────────────────────────────────
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])
            ->middleware('permission:view_roles');

        Route::post('/', [RoleController::class, 'store'])
            ->middleware('permission:add_role');

        Route::get('/{id}', [RoleController::class, 'show'])
            ->middleware('permission:view_roles');

        Route::put('/{id}', [RoleController::class, 'update'])
            ->middleware('permission:edit_role');

        Route::delete('/{id}', [RoleController::class, 'destroy'])
            ->middleware('permission:delete_role');

        Route::post('/{id}/controls', [RoleController::class, 'assignControls'])
            ->middleware('permission:edit_role');

        Route::delete('/{id}/controls', [RoleController::class, 'removeControls'])
            ->middleware('permission:edit_role');
    });

    // ── Modules & Controls ────────────────────────────────────
    Route::prefix('modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index'])
            ->middleware('permission:view_modules');

        Route::post('/', [ModuleController::class, 'store'])
            ->middleware('permission:add_module');

        Route::get('/{id}', [ModuleController::class, 'show'])
            ->middleware('permission:view_modules');

        Route::put('/{id}', [ModuleController::class, 'update'])
            ->middleware('permission:edit_module');

        Route::delete('/{id}', [ModuleController::class, 'destroy'])
            ->middleware('permission:delete_module');

        // Controls nested under module
        Route::get('/{id}/controls', [ModuleController::class, 'controls'])
            ->middleware('permission:view_modules');

        Route::post('/{id}/controls', [ModuleController::class, 'storeControl'])
            ->middleware('permission:add_module');

        // Control-level actions (not nested — uses controlId directly)
        Route::put('/controls/{controlId}', [ModuleController::class, 'updateControl'])
            ->middleware('permission:edit_module');

        Route::delete('/controls/{controlId}', [ModuleController::class, 'destroyControl'])
            ->middleware('permission:delete_module');
    });

    // ── Content (Lessons) ─────────────────────────────────────
    Route::get('learning', [LearningController::class, 'index']);
    Route::get('learning/modules/{id}', [LearningController::class, 'module'])->whereNumber('id');

    Route::prefix('content')->group(function () {
        Route::get('/', [ContentController::class, 'index']);   // all authenticated users
        Route::get('/{id}', [ContentController::class, 'show']);   // all authenticated users
        Route::post('/{id}/open', [LearningController::class, 'open']);
        Route::post('/{id}/complete', [LearningController::class, 'complete']);

        Route::post('/', [ContentController::class, 'store'])
            ->middleware('permission:upload_content');

        Route::put('/{id}', [ContentController::class, 'update'])
            ->middleware('permission:edit_content');

        Route::delete('/{id}', [ContentController::class, 'destroy'])
            ->middleware('permission:delete_content');

        Route::post('/{id}/submit', [ContentController::class, 'submit'])
            ->middleware('permission:upload_content,edit_content');
        Route::patch('/{id}/toggle', [ContentController::class, 'toggle'])
            ->middleware('permission:edit_content');
    });

    // ── Opportunities ─────────────────────────────────────────
    Route::prefix('opportunities')->group(function () {
        Route::get('/', [OpportunityController::class, 'index']);   // all auth users
        Route::get('/{id}', [OpportunityController::class, 'show']);    // all auth users

        Route::post('/', [OpportunityController::class, 'store'])
            ->middleware('permission:add_opportunity');

        Route::put('/{id}', [OpportunityController::class, 'update'])
            ->middleware('permission:edit_opportunity');

        Route::delete('/{id}', [OpportunityController::class, 'destroy'])
            ->middleware('permission:delete_opportunity');
    });

    // ── Feedback (FBK-REQ01, FBK-REQ04) ──────────────────────
    Route::prefix('feedback')->group(function () {
        Route::get('/', [FeedbackController::class, 'index']);
        Route::post('/', [FeedbackController::class, 'store']);
        Route::get('/{reference}', [FeedbackController::class, 'show']);
        Route::post('/{reference}/messages', [FeedbackController::class, 'message']);
    });

    // ── Forum ─────────────────────────────────────────────────
    Route::prefix('forum')->group(function () {
        Route::get('/', [ForumPostController::class, 'index']);   // all auth users
        Route::get('/{id}', [ForumPostController::class, 'show']);    // all auth users

        Route::post('/', [ForumPostController::class, 'store'])
            ->middleware('permission:create_forum_post');

        Route::post('/{id}/reply', [ForumPostController::class, 'reply']); // all auth users

        Route::put('/{id}', [ForumPostController::class, 'update']);    // own post or admin
        Route::delete('/{id}', [ForumPostController::class, 'destroy']); // own post or admin
    });

    // ── Events ────────────────────────────────────────────────
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);   // all auth users
        Route::get('/mine', [EventController::class, 'mine']);
        Route::get('/{id}', [EventController::class, 'show']);    // all auth users
        Route::get('/{id}/summary', [EventController::class, 'summary'])
            ->middleware('permission:view_event_attendees');
        Route::post('/{id}/check-in', [EventController::class, 'checkIn'])
            ->middleware(['permission:check_in_attendees', 'throttle:120,1']);

        Route::post('/', [EventController::class, 'store'])
            ->middleware('permission:add_event');

        Route::put('/{id}', [EventController::class, 'update'])
            ->middleware('permission:edit_event');

        Route::delete('/{id}', [EventController::class, 'destroy'])
            ->middleware('permission:delete_event');

        // Registration actions (any authenticated user)
        Route::post('/{id}/register', [EventController::class, 'register']);
        Route::delete('/{id}/register', [EventController::class, 'cancelRegistration']);

        // Attendee list (admin only)
        Route::get('/{id}/attendees', [EventController::class, 'attendees'])
            ->middleware('permission:view_event_attendees');
    });

    // ── User Progress ─────────────────────────────────────────
    Route::prefix('progress')->group(function () {
        Route::get('/', [UserProgressController::class, 'index']);
        Route::get('/summary', [UserProgressController::class, 'summary']);
        Route::get('/{contentId}', [UserProgressController::class, 'show']);
        Route::post('/', [UserProgressController::class, 'upsert']);
    });
});

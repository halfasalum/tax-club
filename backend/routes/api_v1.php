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
use App\Http\Controllers\API\V1\ForumPostController;
use App\Http\Controllers\API\V1\InstitutionController;
use App\Http\Controllers\API\V1\ModuleController;
use App\Http\Controllers\API\V1\OpportunityController;
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
    Route::post('login',    [AuthController::class, 'login']);
});

// Institutions list is public — needed during registration dropdown
Route::get('institutions', [InstitutionController::class, 'index']);
Route::get('institutions/{id}', [InstitutionController::class, 'show']);

// Global Leaderboard
Route::get('/leaderboard', [QuizController::class, 'globalLeaderboard']);

// ──────────────────────────────────────────────────────────────
//  AUTHENTICATED ROUTES (auth:sanctum)
// ──────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('logout',          [AuthController::class, 'logout']);
        Route::get('me',               [AuthController::class, 'me']);
        Route::post('verify',          [AuthController::class, 'verify']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    // ── Dashboard ─────────────────────────────────────────────
    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('', [DashboardController::class, '']);
    // ── Quizzes ───────────────────────────────────────────────
    Route::prefix('quizzes')->group(function () {
        // Public routes
        Route::get('/', [QuizController::class, 'index']);
        Route::get('/my-attempts', [QuizController::class, 'myAttempts']);
        Route::get('/{id}', [QuizController::class, 'show']);
        Route::get('/{id}/questions', [QuizController::class, 'questions']);
        Route::get('/{id}/manage-questions', [QuizController::class, 'manageQuestions'])
            ->middleware('permission:edit_quiz');
        Route::get('/{id}/leaderboard', [QuizController::class, 'leaderboard']);
        Route::post('/{id}/submit', [QuizController::class, 'submitQuiz']);
        Route::get('/{id}/my-attempts', [QuizController::class, 'myQuizAttempts']);
        Route::get('/{id}/attempt/{attemptId}/results', [QuizController::class, 'attemptResults']);

        // Admin routes (require permissions)
        Route::post('/', [QuizController::class, 'store'])
            ->middleware('permission:create_quiz');

        Route::put('/{id}', [QuizController::class, 'update'])
            ->middleware('permission:edit_quiz');

        Route::delete('/{id}', [QuizController::class, 'destroy'])
            ->middleware('permission:delete_quiz');

        Route::patch('/{id}/toggle', [QuizController::class, 'toggle'])
            ->middleware('permission:edit_quiz');

        // Question management
        Route::post('/{id}/questions', [QuizController::class, 'addQuestion'])
            ->middleware('permission:edit_quiz');

        Route::put('/questions/{questionId}', [QuizController::class, 'updateQuestion'])
            ->middleware('permission:edit_quiz');

        Route::delete('/questions/{questionId}', [QuizController::class, 'deleteQuestion'])
            ->middleware('permission:edit_quiz');

        // Answer management
        Route::post('/questions/{questionId}/answers', [QuizController::class, 'addAnswer'])
            ->middleware('permission:edit_quiz');

        Route::put('/answers/{answerId}', [QuizController::class, 'updateAnswer'])
            ->middleware('permission:edit_quiz');

        Route::delete('/answers/{answerId}', [QuizController::class, 'deleteAnswer'])
            ->middleware('permission:edit_quiz');

        // Reorder questions
        Route::post('/reorder-questions', [QuizController::class, 'reorderQuestions'])
            ->middleware('permission:edit_quiz');
    });


    // ── Users ─────────────────────────────────────────────────
    Route::prefix('users')->group(function () {
        Route::get('/',    [UserController::class, 'index'])
            ->middleware('permission:view_users');

        Route::post('/',   [UserController::class, 'store'])
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
        Route::post('/',      [InstitutionController::class, 'store'])
            ->middleware('permission:add_institution');

        Route::put('/{id}',   [InstitutionController::class, 'update'])
            ->middleware('permission:edit_institution');

        Route::delete('/{id}', [InstitutionController::class, 'destroy'])
            ->middleware('permission:delete_institution');
    });

    // ── Roles ─────────────────────────────────────────────────
    Route::prefix('roles')->group(function () {
        Route::get('/',    [RoleController::class, 'index'])
            ->middleware('permission:view_roles');

        Route::post('/',   [RoleController::class, 'store'])
            ->middleware('permission:add_role');

        Route::get('/{id}',    [RoleController::class, 'show'])
            ->middleware('permission:view_roles');

        Route::put('/{id}',    [RoleController::class, 'update'])
            ->middleware('permission:edit_role');

        Route::delete('/{id}', [RoleController::class, 'destroy'])
            ->middleware('permission:delete_role');

        Route::post('/{id}/controls',   [RoleController::class, 'assignControls'])
            ->middleware('permission:edit_role');

        Route::delete('/{id}/controls', [RoleController::class, 'removeControls'])
            ->middleware('permission:edit_role');
    });

    // ── Modules & Controls ────────────────────────────────────
    Route::prefix('modules')->group(function () {
        Route::get('/',    [ModuleController::class, 'index'])
            ->middleware('permission:view_modules');

        Route::post('/',   [ModuleController::class, 'store'])
            ->middleware('permission:add_module');

        Route::get('/{id}',    [ModuleController::class, 'show'])
            ->middleware('permission:view_modules');

        Route::put('/{id}',    [ModuleController::class, 'update'])
            ->middleware('permission:edit_module');

        Route::delete('/{id}', [ModuleController::class, 'destroy'])
            ->middleware('permission:delete_module');

        // Controls nested under module
        Route::get('/{id}/controls',  [ModuleController::class, 'controls'])
            ->middleware('permission:view_modules');

        Route::post('/{id}/controls', [ModuleController::class, 'storeControl'])
            ->middleware('permission:add_module');

        // Control-level actions (not nested — uses controlId directly)
        Route::put('/controls/{controlId}',    [ModuleController::class, 'updateControl'])
            ->middleware('permission:edit_module');

        Route::delete('/controls/{controlId}', [ModuleController::class, 'destroyControl'])
            ->middleware('permission:delete_module');
    });

    // ── Content (Lessons) ─────────────────────────────────────
    Route::prefix('content')->group(function () {
        Route::get('/',    [ContentController::class, 'index']);   // all authenticated users
        Route::get('/{id}', [ContentController::class, 'show']);   // all authenticated users

        Route::post('/',   [ContentController::class, 'store'])
            ->middleware('permission:upload_content');

        Route::put('/{id}', [ContentController::class, 'update'])
            ->middleware('permission:edit_content');

        Route::delete('/{id}', [ContentController::class, 'destroy'])
            ->middleware('permission:delete_content');

        Route::patch('/{id}/toggle', [ContentController::class, 'toggle'])
            ->middleware('permission:edit_content');
    });



    // ── Opportunities ─────────────────────────────────────────
    Route::prefix('opportunities')->group(function () {
        Route::get('/',     [OpportunityController::class, 'index']);   // all auth users
        Route::get('/{id}', [OpportunityController::class, 'show']);    // all auth users

        Route::post('/',    [OpportunityController::class, 'store'])
            ->middleware('permission:add_opportunity');

        Route::put('/{id}', [OpportunityController::class, 'update'])
            ->middleware('permission:edit_opportunity');

        Route::delete('/{id}', [OpportunityController::class, 'destroy'])
            ->middleware('permission:delete_opportunity');
    });

    // ── Forum ─────────────────────────────────────────────────
    Route::prefix('forum')->group(function () {
        Route::get('/',     [ForumPostController::class, 'index']);   // all auth users
        Route::get('/{id}', [ForumPostController::class, 'show']);    // all auth users

        Route::post('/',    [ForumPostController::class, 'store'])
            ->middleware('permission:create_forum_post');

        Route::post('/{id}/reply', [ForumPostController::class, 'reply']); // all auth users

        Route::put('/{id}', [ForumPostController::class, 'update']);    // own post or admin
        Route::delete('/{id}', [ForumPostController::class, 'destroy']); // own post or admin
    });

    // ── Events ────────────────────────────────────────────────
    Route::prefix('events')->group(function () {
        Route::get('/',     [EventController::class, 'index']);   // all auth users
        Route::get('/{id}', [EventController::class, 'show']);    // all auth users

        Route::post('/',    [EventController::class, 'store'])
            ->middleware('permission:add_event');

        Route::put('/{id}', [EventController::class, 'update'])
            ->middleware('permission:edit_event');

        Route::delete('/{id}', [EventController::class, 'destroy'])
            ->middleware('permission:delete_event');

        // Registration actions (any authenticated user)
        Route::post('/{id}/register',   [EventController::class, 'register']);
        Route::delete('/{id}/register', [EventController::class, 'cancelRegistration']);

        // Attendee list (admin only)
        Route::get('/{id}/attendees', [EventController::class, 'attendees'])
            ->middleware('permission:view_event_attendees');
    });

    // ── User Progress ─────────────────────────────────────────
    Route::prefix('progress')->group(function () {
        Route::get('/',             [UserProgressController::class, 'index']);
        Route::get('/summary',      [UserProgressController::class, 'summary']);
        Route::get('/{contentId}',  [UserProgressController::class, 'show']);
        Route::post('/',            [UserProgressController::class, 'upsert']);
    });
});

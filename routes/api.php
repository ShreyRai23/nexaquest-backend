<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\MentorController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\ProfileController;

// Health check
Route::get('/health', fn() => response()->json(['status' => 'ok', 'app' => 'MindBloom AI', 'time' => now()->toISOString()]));

// ─── Public Auth Routes ──────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ─── Protected Routes (JWT required) ────────────────────────
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout',  [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me',       [AuthController::class, 'me']);
    });

    // Profile (supports both PUT and POST with _method=PUT for file uploads)
    Route::get('/profile',    [ProfileController::class, 'show']);
    Route::put('/profile',    [ProfileController::class, 'update']);
    Route::post('/profile',   [ProfileController::class, 'update']);

    // Child-only routes
    Route::middleware('role:child')->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Quizzes
        Route::get('/quizzes',               [QuizController::class, 'index']);
        Route::get('/quizzes/history',       [QuizController::class, 'history']);
        Route::get('/quizzes/{id}',          [QuizController::class, 'show']);
        Route::post('/quizzes/{id}/submit',  [QuizController::class, 'submit']);

        // Skills
        Route::get('/skills',          [SkillController::class, 'index']);
        Route::get('/skills/progress', [SkillController::class, 'progress']);

        // Missions
        Route::get('/missions/today',         [MissionController::class, 'today']);
        Route::get('/missions',               [MissionController::class, 'all']);
        Route::post('/missions/{id}/claim',   [MissionController::class, 'claim']);

        // Achievements
        Route::get('/achievements',          [AchievementController::class, 'index']);
        Route::get('/achievements/unlocked', [AchievementController::class, 'unlocked']);

        // Mentor (AI Chatbot)
        Route::get('/mentor/conversations',                           [MentorController::class, 'conversations']);
        Route::post('/mentor/conversations',                          [MentorController::class, 'createConversation']);
        Route::delete('/mentor/conversations/{id}',                  [MentorController::class, 'deleteConversation']);
        Route::get('/mentor/conversations/{id}/messages',            [MentorController::class, 'messages']);
        Route::post('/mentor/conversations/{id}/messages',           [MentorController::class, 'sendMessage']);

        // Reports
        Route::get('/reports/latest',      [ReportController::class, 'latest']);
        Route::post('/reports/generate',   [ReportController::class, 'generate']);
        Route::get('/reports/{id}/pdf',    [ReportController::class, 'downloadPdf']);
    });

    // Parent-only routes
    Route::middleware('role:parent')->group(function () {
        Route::get('/parent/dashboard',                    [ParentController::class, 'dashboard']);
        Route::post('/parent/children',                    [ParentController::class, 'createChild']);
        Route::get('/parent/children/{id}',                [ParentController::class, 'childDetail']);
        Route::get('/parent/children/{id}/progress',       [ParentController::class, 'childProgress']);
        Route::post('/parent/link/{childId}',              [ParentController::class, 'linkChild']);
        Route::post('/parent/children/{id}/report',        [ParentController::class, 'generateReport']);
        Route::get('/parent/children/{id}/report/latest',  [ParentController::class, 'latestReport']);
        Route::get('/parent/children/{id}/report/{rid}/pdf', [ParentController::class, 'downloadReport']);
    });
});

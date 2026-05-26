<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Services\GamificationService;

class DashboardController extends Controller
{
    public function __construct(
        private GeminiService $gemini,
        private GamificationService $gamification
    ) {}

    public function index()
    {
        $user  = auth('api')->user();
        $child = $user->childProfile;
        if (!$child) return response()->json(['message' => 'Child profile not found'], 404);

        $child->load(['skillScores', 'quizAttempts' => fn($q) => $q->latest()->take(5), 'userAchievements.achievement']);

        $skillScores = $child->skillScores->map(fn($s) => [
            'category' => $s->category, 'score' => $s->score
        ])->toArray();

        // Get AI daily tips (cached per child per day)
        $cacheKey = "daily_tips_{$child->id}_" . now()->format('Y-m-d');
        $tips = cache()->remember($cacheKey, 3600 * 8, function () use ($child, $skillScores) {
            return $this->gemini->generateDailyTips($child->hero_name ?? 'Explorer', $skillScores);
        });

        // Update streak
        $streakResult = $this->gamification->updateStreak($child);

        // Sum XP from all sources today:
        // 1. Quiz attempts completed today
        $quizXP = $child->quizAttempts()
            ->whereDate('completed_at', now()->toDateString())
            ->sum('xp_earned');

        // 2. Achievements unlocked today (their xp_bonus)
        $achievementXP = $child->userAchievements()
            ->with('achievement')
            ->whereDate('unlocked_at', now()->toDateString())
            ->get()
            ->sum(fn($ua) => $ua->achievement?->xp_bonus ?? 0);

        // 3. Streak bonus earned today
        $streakXP = \App\Models\DailyStreak::where('child_profile_id', $child->id)
            ->whereDate('streak_date', now()->toDateString())
            ->sum('xp_earned');

        $todayXP = $quizXP + $achievementXP + $streakXP;

        return response()->json([
            'child' => [
                'id'           => $child->id,
                'hero_name'    => $child->hero_name ?? $user->name,
                'avatar_emoji' => $child->avatar_emoji,
                'level'        => $child->level,
                'xp'           => $child->xp,
                'streak_count' => $child->streak_count,
                'xp_to_next_level'   => $child->xp_to_next_level,
                'level_progress_pct' => $child->level_progress_percent,
            ],
            'stats' => [
                'today_xp'       => $todayXP,
                'badges_count'   => $child->userAchievements->count(),
                'quizzes_taken'  => $child->quizAttempts->count(),
                'streak'         => $child->streak_count,
            ],
            'skill_scores'   => $skillScores,
            'ai_tips'        => $tips,
            'recent_attempts'=> $child->quizAttempts->take(5),
            'streak_result'  => $streakResult,
        ]);
    }
}

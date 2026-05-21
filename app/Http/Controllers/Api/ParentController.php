<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use App\Models\AiReport;
use App\Models\CareerRecommendation;
use App\Models\QuizAttempt;
use App\Services\GeminiService;
use App\Services\GamificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ParentController extends Controller
{
    public function __construct(
        private GeminiService $gemini,
        private GamificationService $gamification,
    ) {}

    private function getOwnedChild(int $childId): ChildProfile
    {
        $parent = auth('api')->user();
        return ChildProfile::where('id', $childId)
            ->where('parent_id', $parent->id)
            ->firstOrFail();
    }

    public function dashboard()
    {
        $parent = auth('api')->user();
        $children = $parent->children()->with([
            'user',
            'skillScores',
            'quizAttempts' => fn($q) => $q->latest()->take(7),
            'userAchievements',
        ])->get();

        $childrenData = $children->map(function ($child) {
            $latestReport = AiReport::where('child_profile_id', $child->id)->latest()->first();
            $careers = CareerRecommendation::where('child_profile_id', $child->id)
                ->orderByDesc('match_percentage')->take(3)->get();

            $skills    = $child->skillScores;
            $strongest = $skills->sortByDesc('score')->first();
            $weakest   = $skills->sortBy('score')->first();

            $weeklyScores = $child->quizAttempts()
                ->whereDate('completed_at', '>=', now()->subDays(7))
                ->pluck('score')->toArray();

            $avgScore = !empty($weeklyScores) ? round(array_sum($weeklyScores) / count($weeklyScores)) : 0;

            $todayMissions = $child->missionProgress()
                ->whereDate('assigned_date', today())->get();

            return [
                'id'               => $child->id,
                'name'             => $child->user->name,
                'hero_name'        => $child->hero_name,
                'avatar_emoji'     => $child->avatar_emoji,
                'level'            => $child->level,
                'xp'               => $child->xp,
                'streak_count'     => $child->streak_count,
                'skill_scores'     => $skills,
                'strongest_skill'  => $strongest?->category,
                'strongest_score'  => $strongest?->score,
                'weakest_skill'    => $weakest?->category,
                'avg_weekly_score' => $avgScore,
                'badges_count'     => $child->userAchievements->count(),
                'quizzes_taken'    => $child->quizAttempts->count(),
                'latest_report'    => $latestReport,
                'top_careers'      => $careers,
                'missions_today'   => $todayMissions->count(),
                'missions_done'    => $todayMissions->where('status', 'completed')->count(),
                'recent_activity'  => $child->quizAttempts->map(fn($a) => [
                    'quiz_id'      => $a->quiz_id,
                    'score'        => $a->score,
                    'xp_earned'    => $a->xp_earned,
                    'completed_at' => $a->completed_at,
                ]),
            ];
        });

        return response()->json([
            'parent'   => $parent->only(['id', 'name', 'email']),
            'children' => $childrenData,
        ]);
    }

    public function childDetail(int $childId)
    {
        $child = $this->getOwnedChild($childId);
        $child->load(['user', 'skillScores', 'quizAttempts.quiz', 'userAchievements.achievement']);
        $report  = AiReport::where('child_profile_id', $child->id)->latest()->first();
        $careers = CareerRecommendation::where('child_profile_id', $child->id)
            ->orderByDesc('match_percentage')->get();

        return response()->json(['child' => $child, 'report' => $report, 'careers' => $careers]);
    }

    public function childProgress(int $childId)
    {
        $child = $this->getOwnedChild($childId);

        // Daily quiz counts + avg scores for last 14 days
        $daily = collect(range(13, 0))->map(function ($daysAgo) use ($child) {
            $date     = Carbon::today()->subDays($daysAgo);
            $attempts = QuizAttempt::where('child_profile_id', $child->id)
                ->whereDate('completed_at', $date)->get();
            return [
                'date'      => $date->format('M d'),
                'quizzes'   => $attempts->count(),
                'avg_score' => $attempts->count() ? round($attempts->avg('score')) : 0,
                'xp_earned' => $attempts->sum('xp_earned'),
            ];
        });

        // Full quiz history (last 30)
        $history = QuizAttempt::where('child_profile_id', $child->id)
            ->with('quiz')->latest('completed_at')->take(30)->get()
            ->map(fn($a) => [
                'quiz_name'    => $a->quiz?->title ?? "Quiz #{$a->quiz_id}",
                'category'     => $a->quiz?->category ?? 'General',
                'score'        => $a->score,
                'xp_earned'    => $a->xp_earned,
                'completed_at' => $a->completed_at?->toDateTimeString(),
            ]);

        $achievements = $child->userAchievements()->with('achievement')->get()
            ->map(fn($ua) => [
                'name'      => $ua->achievement?->name,
                'emoji'     => $ua->achievement?->emoji ?? '🏆',
                'earned_at' => $ua->created_at?->toDateString(),
            ]);

        return response()->json([
            'child' => [
                'id'           => $child->id,
                'hero_name'    => $child->hero_name,
                'avatar_emoji' => $child->avatar_emoji,
                'level'        => $child->level,
                'xp'           => $child->xp,
                'streak_count' => $child->streak_count,
            ],
            'daily_activity' => $daily->values(),
            'quiz_history'   => $history,
            'skill_scores'   => $child->skillScores,
            'achievements'   => $achievements,
        ]);
    }

    public function latestReport(int $childId)
    {
        $child   = $this->getOwnedChild($childId);
        $report  = AiReport::where('child_profile_id', $child->id)->latest()->first();
        $careers = CareerRecommendation::where('child_profile_id', $child->id)
            ->orderByDesc('match_percentage')->get();

        return response()->json([
            'report'  => $report,
            'careers' => $careers,
            'child'   => $child->load('user'),
        ]);
    }

    public function generateReport(int $childId)
    {
        $child = $this->getOwnedChild($childId);
        $child->load(['user', 'skillScores', 'quizAttempts', 'userAchievements.achievement', 'userInterests.category', 'dailyStreak']);

        $skillScores = $child->skillScores->map(fn($s) => [
            'category' => $s->category, 'score' => $s->score,
        ])->toArray();

        if (empty($skillScores)) {
            return response()->json(['message' => 'Child needs to complete at least one quiz first!'], 400);
        }

        $childName    = $child->hero_name ?? $child->user->name;
        $totalQuizzes = $child->quizAttempts->count();
        $achievements = $child->userAchievements->pluck('achievement.name')->filter()->toArray();
        $streak       = $child->dailyStreak?->current_streak ?? 0;

        $reportData = $this->gemini->generateAptitudeReport(
            $childName, $child->age ?? 12, $skillScores,
            $child->level, $child->xp, $totalQuizzes, $achievements, $streak
        );

        $report = AiReport::create([
            'child_profile_id'      => $child->id,
            'summary'               => $reportData['summary'],
            'top_strength'          => $reportData['top_strength'],
            'learning_style'        => $reportData['learning_style'],
            'personality_type'      => $reportData['personality_type'],
            'strengths_json'        => $reportData['strengths'],
            'weaknesses_json'       => $reportData['weaknesses'],
            'recommendations_json'  => $reportData['recommendations'],
            'skill_scores_snapshot' => $skillScores,
            'report_date'           => now()->toDateString(),
        ]);

        $interests  = $child->userInterests->pluck('category.name')->filter()->toArray();
        $careerData = $this->gemini->generateCareerRecommendations($skillScores, $interests);

        CareerRecommendation::where('child_profile_id', $child->id)->delete();
        foreach ($careerData as $career) {
            CareerRecommendation::create([
                'child_profile_id' => $child->id,
                'career_title'     => $career['career_title'],
                'career_emoji'     => $career['career_emoji'] ?? '🚀',
                'match_percentage' => $career['match_percentage'],
                'ai_reasoning'     => $career['ai_reasoning'],
                'skills_needed'    => $career['skills_needed'] ?? [],
                'suggested_at'     => now(),
            ]);
        }

        return response()->json([
            'message' => 'AI Report generated! ✨',
            'report'  => $report->fresh(),
            'careers' => CareerRecommendation::where('child_profile_id', $child->id)->get(),
        ]);
    }

    public function downloadReport(int $childId, int $rid)
    {
        $child   = $this->getOwnedChild($childId);
        $report  = AiReport::where('id', $rid)->where('child_profile_id', $child->id)->firstOrFail();
        $careers = CareerRecommendation::where('child_profile_id', $child->id)
            ->orderByDesc('match_percentage')->take(4)->get();

        $pdf = Pdf::loadView('reports.aptitude', [
            'report'  => $report,
            'child'   => $child->load('user'),
            'careers' => $careers,
        ]);

        return $pdf->download('mindbloom-report-' . $report->report_date . '.pdf');
    }

    public function linkChild(int $childId)
    {
        $parent = auth('api')->user();
        $child  = ChildProfile::findOrFail($childId);

        if ($child->parent_id && $child->parent_id !== $parent->id) {
            return response()->json(['message' => 'Child already linked to another parent.'], 403);
        }

        $child->update(['parent_id' => $parent->id]);
        return response()->json(['message' => 'Child linked successfully! 👨‍👧']);
    }

    public function createChild(\Illuminate\Http\Request $request)
    {
        $parent = auth('api')->user();
        if ($parent->role !== 'parent') {
            return response()->json(['message' => 'Only parents can create child accounts.'], 403);
        }

        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|string|min:6',
            'hero_name' => 'nullable|string|max:50',
            'age'       => 'nullable|integer|min:5|max:20',
        ]);

        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        $user = \App\Models\User::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'password'     => $request->password,
            'role'         => 'child',
            'avatar_emoji' => '🦊',
        ]);

        $childProfile = ChildProfile::create([
            'user_id'   => $user->id,
            'parent_id' => $parent->id,
            'hero_name' => $request->hero_name ?? $request->name,
            'age'       => $request->age,
        ]);

        return response()->json([
            'message' => 'Child profile created successfully! 🌟',
            'child'   => $childProfile->load('user'),
        ], 201);
    }
}

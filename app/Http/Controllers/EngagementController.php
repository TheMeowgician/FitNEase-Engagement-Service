<?php

namespace App\Http\Controllers;

use App\Models\EngagementMetric;
use App\Models\UserAchievement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class EngagementController extends Controller
{
    public function trackEngagementMetrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'metric_date' => 'sometimes|date',
            'session_count' => 'sometimes|integer|min:0',
            'total_session_duration_minutes' => 'sometimes|integer|min:0',
            'achievements_earned' => 'sometimes|integer|min:0',
            'points_earned' => 'sometimes|integer|min:0',
            'notification_interactions' => 'sometimes|integer|min:0',
            'feature_usage_json' => 'sometimes|array',
            'motivation_level' => 'sometimes|in:very_low,low,moderate,high,very_high'
        ]);

        $metricDate = $validated['metric_date'] ?? Carbon::today()->toDateString();

        $metric = EngagementMetric::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'metric_date' => $metricDate
            ],
            array_merge($validated, [
                'engagement_score' => $this->calculateEngagementScore($validated),
                'metric_date' => $metricDate
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Engagement metrics tracked successfully',
            'data' => $metric
        ]);
    }

    public function getUserStats($userId): JsonResponse
    {
        $metrics = EngagementMetric::forUser($userId)
            ->orderBy('metric_date', 'desc')
            ->take(30)
            ->get();

        $totalPoints = UserAchievement::forUser($userId)
            ->completed()
            ->sum('points_earned');

        $totalAchievements = UserAchievement::forUser($userId)
            ->completed()
            ->count();

        $avgEngagementScore = $metrics->avg('engagement_score') ?? 0;
        $currentStreak = $this->calculateCurrentStreak($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'total_points' => $totalPoints,
                'total_achievements' => $totalAchievements,
                'average_engagement_score' => round($avgEngagementScore, 2),
                'current_streak_days' => $currentStreak,
                'recent_metrics' => $metrics
            ]
        ]);
    }

    public function getLeaderboard(Request $request): JsonResponse
    {
        $timeframe = $request->get('timeframe', 'all_time');
        $limit = $request->get('limit', 10);

        $query = UserAchievement::completed()
            ->selectRaw('user_id, SUM(points_earned) as total_points, COUNT(*) as total_achievements')
            ->groupBy('user_id');

        if ($timeframe === 'this_month') {
            $query->whereMonth('earned_at', Carbon::now()->month)
                  ->whereYear('earned_at', Carbon::now()->year);
        } elseif ($timeframe === 'this_week') {
            $query->whereBetween('earned_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ]);
        }

        $leaderboard = $query->orderBy('total_points', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'rank' => $index + 1,
                    'user_id' => $item->user_id,
                    'total_points' => $item->total_points,
                    'total_achievements' => $item->total_achievements
                ];
            });

        return response()->json([
            'success' => true,
            'timeframe' => $timeframe,
            'data' => $leaderboard
        ]);
    }

    private function calculateEngagementScore(array $data): float
    {
        $sessionScore = ($data['session_count'] ?? 0) * 2;
        $achievementScore = ($data['achievements_earned'] ?? 0) * 5;
        $interactionScore = ($data['notification_interactions'] ?? 0) * 1;

        $totalScore = $sessionScore + $achievementScore + $interactionScore;

        return min($totalScore, 10.00);
    }

    private function calculateCurrentStreak($userId): int
    {
        $metrics = EngagementMetric::forUser($userId)
            ->where('session_count', '>', 0)
            ->orderBy('metric_date', 'desc')
            ->get();

        $streak = 0;
        $expectedDate = Carbon::today();

        foreach ($metrics as $metric) {
            if ($metric->metric_date->equalTo($expectedDate)) {
                $streak++;
                $expectedDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }
}

<?php

namespace App\Services;

use App\Models\EngagementMetric;
use Carbon\Carbon;

class EngagementService
{
    public function calculateEngagementScore($userId, $date): float
    {
        $metrics = EngagementMetric::where('user_id', $userId)
            ->where('metric_date', $date)
            ->first();

        if (!$metrics) {
            return 0.00;
        }

        $score = ($metrics->session_count * 2) +
                 ($metrics->achievements_earned * 5) +
                 ($metrics->notification_interactions * 1);

        return min($score, 10.00);
    }

    public function updateDailyEngagement($userId, array $data): EngagementMetric
    {
        $date = $data['metric_date'] ?? Carbon::today()->toDateString();

        $engagementScore = $this->calculateEngagementScore($userId, $date);

        $metric = EngagementMetric::updateOrCreate(
            [
                'user_id' => $userId,
                'metric_date' => $date
            ],
            array_merge($data, [
                'engagement_score' => $engagementScore,
                'metric_date' => $date
            ])
        );

        return $metric;
    }

    public function getEngagementTrend($userId, $days = 30): array
    {
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($days);

        $metrics = EngagementMetric::forUser($userId)
            ->dateRange($startDate, $endDate)
            ->orderBy('metric_date', 'asc')
            ->get();

        $trend = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $metric = $metrics->firstWhere('metric_date', $currentDate->toDateString());

            $trend[] = [
                'date' => $currentDate->toDateString(),
                'engagement_score' => $metric ? $metric->engagement_score : 0.00,
                'session_count' => $metric ? $metric->session_count : 0,
                'total_session_duration_minutes' => $metric ? $metric->total_session_duration_minutes : 0,
                'achievements_earned' => $metric ? $metric->achievements_earned : 0,
                'motivation_level' => $metric ? $metric->motivation_level : 'moderate'
            ];

            $currentDate->addDay();
        }

        return $trend;
    }

    public function calculateWeeklyEngagement($userId): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $weeklyMetrics = EngagementMetric::forUser($userId)
            ->dateRange($startOfWeek, $endOfWeek)
            ->get();

        $totalSessions = $weeklyMetrics->sum('session_count');
        $totalDuration = $weeklyMetrics->sum('total_session_duration_minutes');
        $totalAchievements = $weeklyMetrics->sum('achievements_earned');
        $avgEngagementScore = $weeklyMetrics->avg('engagement_score') ?? 0;

        return [
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString(),
            'total_sessions' => $totalSessions,
            'total_duration_minutes' => $totalDuration,
            'total_achievements' => $totalAchievements,
            'average_engagement_score' => round($avgEngagementScore, 2),
            'active_days' => $weeklyMetrics->where('session_count', '>', 0)->count()
        ];
    }

    public function getMotivationInsights($userId): array
    {
        $last30Days = EngagementMetric::forUser($userId)
            ->where('metric_date', '>=', Carbon::today()->subDays(30))
            ->get();

        $motivationDistribution = $last30Days->groupBy('motivation_level')
            ->map(function ($items) {
                return $items->count();
            });

        $currentMotivation = $last30Days->sortByDesc('metric_date')->first();
        $avgEngagement = $last30Days->avg('engagement_score') ?? 0;

        $motivationTrend = $this->calculateMotivationTrend($userId, 7);

        return [
            'current_motivation_level' => $currentMotivation ? $currentMotivation->motivation_level : 'moderate',
            'average_engagement_score' => round($avgEngagement, 2),
            'motivation_distribution' => $motivationDistribution,
            'motivation_trend' => $motivationTrend,
            'recommendation' => $this->getMotivationRecommendation($avgEngagement, $currentMotivation ? $currentMotivation->motivation_level : 'moderate')
        ];
    }

    private function calculateMotivationTrend($userId, $days): array
    {
        $metrics = EngagementMetric::forUser($userId)
            ->where('metric_date', '>=', Carbon::today()->subDays($days))
            ->orderBy('metric_date', 'asc')
            ->get();

        return $metrics->map(function ($metric) {
            return [
                'date' => $metric->metric_date,
                'motivation_level' => $metric->motivation_level,
                'engagement_score' => $metric->engagement_score
            ];
        })->toArray();
    }

    private function getMotivationRecommendation($avgEngagement, $currentMotivation): string
    {
        if ($avgEngagement >= 8.0 && in_array($currentMotivation, ['high', 'very_high'])) {
            return 'Excellent engagement! Keep up the great work.';
        } elseif ($avgEngagement >= 6.0 && in_array($currentMotivation, ['moderate', 'high'])) {
            return 'Good engagement level. Consider setting new challenges to maintain momentum.';
        } elseif ($avgEngagement >= 4.0) {
            return 'Moderate engagement. Try exploring new features or setting achievable goals.';
        } else {
            return 'Low engagement detected. Consider reviewing your goals and trying shorter, more frequent sessions.';
        }
    }
}
<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\UserAchievement;

class AchievementService
{
    public function checkWorkoutCountAchievement($userId, $workoutCount): array
    {
        $achievements = Achievement::where('achievement_type', 'workout_count')
            ->where('is_active', true)
            ->get();

        $unlockedAchievements = [];

        foreach ($achievements as $achievement) {
            $criteria = json_decode($achievement->criteria_json, true);
            if ($workoutCount >= $criteria['target_count']) {
                $unlockedAchievement = $this->unlockAchievement($userId, $achievement->achievement_id);
                $unlockedAchievements[] = $unlockedAchievement;
            }
        }

        return $unlockedAchievements;
    }

    public function checkStreakAchievement($userId, $streakCount): array
    {
        $achievements = Achievement::where('achievement_type', 'streak')
            ->where('is_active', true)
            ->get();

        $unlockedAchievements = [];

        foreach ($achievements as $achievement) {
            $criteria = json_decode($achievement->criteria_json, true);
            if ($streakCount >= $criteria['target_streak']) {
                $unlockedAchievement = $this->unlockAchievement($userId, $achievement->achievement_id);
                $unlockedAchievements[] = $unlockedAchievement;
            }
        }

        return $unlockedAchievements;
    }

    public function checkCalorieAchievement($userId, $caloriesBurned): array
    {
        $achievements = Achievement::where('achievement_type', 'calories')
            ->where('is_active', true)
            ->get();

        $unlockedAchievements = [];

        foreach ($achievements as $achievement) {
            $criteria = json_decode($achievement->criteria_json, true);
            if ($caloriesBurned >= $criteria['target_calories']) {
                $unlockedAchievement = $this->unlockAchievement($userId, $achievement->achievement_id);
                $unlockedAchievements[] = $unlockedAchievement;
            }
        }

        return $unlockedAchievements;
    }

    public function checkTimeAchievement($userId, $totalMinutes): array
    {
        $achievements = Achievement::where('achievement_type', 'time')
            ->where('is_active', true)
            ->get();

        $unlockedAchievements = [];

        foreach ($achievements as $achievement) {
            $criteria = json_decode($achievement->criteria_json, true);
            if ($totalMinutes >= $criteria['target_minutes']) {
                $unlockedAchievement = $this->unlockAchievement($userId, $achievement->achievement_id);
                $unlockedAchievements[] = $unlockedAchievement;
            }
        }

        return $unlockedAchievements;
    }

    public function unlockAchievement($userId, $achievementId): UserAchievement
    {
        $achievement = Achievement::findOrFail($achievementId);

        $userAchievement = UserAchievement::updateOrCreate(
            [
                'user_id' => $userId,
                'achievement_id' => $achievementId
            ],
            [
                'progress_percentage' => 100.00,
                'is_completed' => true,
                'earned_at' => now(),
                'points_earned' => $achievement->points_value
            ]
        );

        return $userAchievement;
    }

    public function updateAchievementProgress($userId, $achievementId, $progressPercentage): UserAchievement
    {
        $isCompleted = $progressPercentage >= 100.00;

        $userAchievement = UserAchievement::updateOrCreate(
            [
                'user_id' => $userId,
                'achievement_id' => $achievementId
            ],
            [
                'progress_percentage' => $progressPercentage,
                'is_completed' => $isCompleted,
                'earned_at' => $isCompleted ? now() : null
            ]
        );

        if ($isCompleted && $userAchievement->wasRecentlyCreated) {
            $achievement = Achievement::find($achievementId);
            $userAchievement->update(['points_earned' => $achievement->points_value]);
        }

        return $userAchievement;
    }

    public function getUserAchievementsSummary($userId): array
    {
        $totalAchievements = Achievement::active()->count();
        $completedAchievements = UserAchievement::forUser($userId)->completed()->count();
        $totalPoints = UserAchievement::forUser($userId)->completed()->sum('points_earned');

        $recentAchievements = UserAchievement::with('achievement')
            ->forUser($userId)
            ->completed()
            ->orderBy('earned_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'total_achievements' => $totalAchievements,
            'completed_achievements' => $completedAchievements,
            'completion_percentage' => $totalAchievements > 0 ? round(($completedAchievements / $totalAchievements) * 100, 2) : 0,
            'total_points' => $totalPoints,
            'recent_achievements' => $recentAchievements
        ];
    }
}
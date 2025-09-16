<?php

namespace App\Http\Controllers;

use App\Models\UserAchievement;
use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GamificationController extends Controller
{
    public function getUserPoints($userId): JsonResponse
    {
        $totalPoints = UserAchievement::forUser($userId)
            ->completed()
            ->sum('points_earned');

        $pointsBreakdown = UserAchievement::with('achievement')
            ->forUser($userId)
            ->completed()
            ->get()
            ->groupBy('achievement.rarity_level')
            ->map(function ($achievements) {
                return $achievements->sum('points_earned');
            });

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'total_points' => $totalPoints,
                'points_breakdown' => $pointsBreakdown
            ]
        ]);
    }

    public function awardPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'achievement_id' => 'required|integer|exists:achievements,achievement_id',
            'points' => 'sometimes|integer|min:0',
            'activity_type' => 'required|string',
            'activity_data' => 'sometimes|array'
        ]);

        $achievement = Achievement::findOrFail($validated['achievement_id']);
        $pointsToAward = $validated['points'] ?? $achievement->points_value;

        $userAchievement = UserAchievement::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'achievement_id' => $validated['achievement_id']
            ],
            [
                'progress_percentage' => 100.00,
                'is_completed' => true,
                'earned_at' => now(),
                'points_earned' => $pointsToAward
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Points awarded successfully',
            'data' => [
                'points_awarded' => $pointsToAward,
                'activity_type' => $validated['activity_type'],
                'user_achievement' => $userAchievement->load('achievement')
            ]
        ]);
    }

    public function getAchievementProgress($userId): JsonResponse
    {
        $allAchievements = Achievement::active()->get();
        $userAchievements = UserAchievement::forUser($userId)
            ->get()
            ->keyBy('achievement_id');

        $progressData = $allAchievements->map(function ($achievement) use ($userAchievements) {
            $userAchievement = $userAchievements->get($achievement->achievement_id);

            return [
                'achievement_id' => $achievement->achievement_id,
                'achievement_name' => $achievement->achievement_name,
                'description' => $achievement->description,
                'achievement_type' => $achievement->achievement_type,
                'rarity_level' => $achievement->rarity_level,
                'points_value' => $achievement->points_value,
                'badge_icon' => $achievement->badge_icon,
                'badge_color' => $achievement->badge_color,
                'progress_percentage' => $userAchievement ? $userAchievement->progress_percentage : 0.00,
                'is_completed' => $userAchievement ? $userAchievement->is_completed : false,
                'earned_at' => $userAchievement ? $userAchievement->earned_at : null,
                'points_earned' => $userAchievement ? $userAchievement->points_earned : 0
            ];
        });

        $completionStats = [
            'total_achievements' => $allAchievements->count(),
            'completed_achievements' => $progressData->where('is_completed', true)->count(),
            'in_progress_achievements' => $progressData->where('progress_percentage', '>', 0)
                ->where('is_completed', false)->count(),
            'completion_percentage' => $allAchievements->count() > 0
                ? round(($progressData->where('is_completed', true)->count() / $allAchievements->count()) * 100, 2)
                : 0
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'stats' => $completionStats,
                'achievements' => $progressData
            ]
        ]);
    }
}

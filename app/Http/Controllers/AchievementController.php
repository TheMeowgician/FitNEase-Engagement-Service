<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Services\CommsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AchievementController extends Controller
{
    protected $commsService;

    public function __construct(CommsService $commsService)
    {
        $this->commsService = $commsService;
    }

    public function getUserAchievements(Request $request, $userId): JsonResponse
    {
        // Verify user is accessing their own data or has permission
        $authenticatedUserId = $request->attributes->get('user_id');
        if ($authenticatedUserId != $userId) {
            return response()->json(['message' => 'Unauthorized access to user data.'], 403);
        }

        $userAchievements = UserAchievement::with('achievement')
            ->forUser($userId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $userAchievements
        ]);
    }

    public function unlockAchievement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'achievement_id' => 'required|integer|exists:achievements,achievement_id',
            'progress_percentage' => 'sometimes|numeric|min:0|max:100',
            'points_earned' => 'sometimes|integer|min:0'
        ]);

        // Verify user is accessing their own data
        $authenticatedUserId = $request->attributes->get('user_id');
        if ($authenticatedUserId != $validated['user_id']) {
            return response()->json(['message' => 'Unauthorized access to user data.'], 403);
        }

        $achievement = Achievement::findOrFail($validated['achievement_id']);

        $userAchievement = UserAchievement::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'achievement_id' => $validated['achievement_id']
            ],
            [
                'progress_percentage' => $validated['progress_percentage'] ?? 100.00,
                'is_completed' => true,
                'earned_at' => now(),
                'points_earned' => $validated['points_earned'] ?? $achievement->points_value
            ]
        );

        // Send notification via Comms Service
        $token = $request->bearerToken();
        $this->commsService->sendAchievementNotification($token, $validated['user_id'], $validated['achievement_id']);

        return response()->json([
            'success' => true,
            'message' => 'Achievement unlocked successfully',
            'data' => $userAchievement->load('achievement')
        ]);
    }

    public function getAvailableAchievements(): JsonResponse
    {
        $achievements = Achievement::active()->get();

        return response()->json([
            'success' => true,
            'data' => $achievements
        ]);
    }

    public function updateAchievementProgress(Request $request, $userId): JsonResponse
    {
        // Verify user is accessing their own data
        $authenticatedUserId = $request->attributes->get('user_id');
        if ($authenticatedUserId != $userId) {
            return response()->json(['message' => 'Unauthorized access to user data.'], 403);
        }

        $validated = $request->validate([
            'achievement_id' => 'required|integer|exists:achievements,achievement_id',
            'progress_percentage' => 'required|numeric|min:0|max:100'
        ]);

        $userAchievement = UserAchievement::updateOrCreate(
            [
                'user_id' => $userId,
                'achievement_id' => $validated['achievement_id']
            ],
            [
                'progress_percentage' => $validated['progress_percentage'],
                'is_completed' => $validated['progress_percentage'] >= 100.00,
                'earned_at' => $validated['progress_percentage'] >= 100.00 ? now() : null
            ]
        );

        if ($userAchievement->is_completed && $userAchievement->wasRecentlyCreated) {
            $achievement = Achievement::find($validated['achievement_id']);
            $userAchievement->update(['points_earned' => $achievement->points_value]);

            // Send notification for completed achievement
            $token = $request->bearerToken();
            $this->commsService->sendAchievementNotification($token, $userId, $validated['achievement_id']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Achievement progress updated',
            'data' => $userAchievement->load('achievement')
        ]);
    }

    public function getAchievementProgress($userId): JsonResponse
    {
        $progress = UserAchievement::with('achievement')
            ->forUser($userId)
            ->get()
            ->map(function ($userAchievement) {
                return [
                    'achievement_id' => $userAchievement->achievement_id,
                    'achievement_name' => $userAchievement->achievement->achievement_name,
                    'progress_percentage' => $userAchievement->progress_percentage,
                    'is_completed' => $userAchievement->is_completed,
                    'points_earned' => $userAchievement->points_earned,
                    'earned_at' => $userAchievement->earned_at
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $progress
        ]);
    }

    /**
     * Check and unlock achievements after a workout
     * This endpoint fetches user stats and checks all achievement criteria
     */
    public function checkAchievements(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
        ]);

        $userId = $validated['user_id'];
        $token = $request->bearerToken();

        // Get all active achievements
        $achievements = Achievement::active()->get();

        // Get already completed achievements for this user
        $completedAchievementIds = UserAchievement::forUser($userId)
            ->completed()
            ->pluck('achievement_id')
            ->toArray();

        // Fetch user stats from tracking service
        $userStats = $this->fetchUserStatsFromTracking($token, $userId);

        $newlyUnlocked = [];

        foreach ($achievements as $achievement) {
            // Skip if already completed
            if (in_array($achievement->achievement_id, $completedAchievementIds)) {
                continue;
            }

            // Check if criteria is met
            $criteriaMet = $this->checkAchievementCriteria($achievement, $userStats);

            if ($criteriaMet) {
                // Unlock the achievement
                $userAchievement = UserAchievement::create([
                    'user_id' => $userId,
                    'achievement_id' => $achievement->achievement_id,
                    'progress_percentage' => 100.00,
                    'is_completed' => true,
                    'earned_at' => now(),
                    'points_earned' => $achievement->points_value,
                ]);

                // Add to newly unlocked list with full achievement details
                $newlyUnlocked[] = [
                    'user_achievement_id' => $userAchievement->user_achievement_id,
                    'achievement_id' => $achievement->achievement_id,
                    'achievement_name' => $achievement->achievement_name,
                    'description' => $achievement->description,
                    'badge_icon' => $achievement->badge_icon,
                    'badge_color' => $achievement->badge_color,
                    'rarity_level' => $achievement->rarity_level,
                    'points_value' => $achievement->points_value,
                    'points_earned' => $userAchievement->points_earned,
                    'earned_at' => $userAchievement->earned_at,
                ];

                // Send notification for the achievement
                try {
                    $this->commsService->sendAchievementNotification($token, $userId, $achievement->achievement_id);
                } catch (\Exception $e) {
                    \Log::warning("Failed to send achievement notification: " . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'newly_unlocked' => $newlyUnlocked,
                'total_unlocked' => count($newlyUnlocked),
            ]
        ]);
    }

    /**
     * Get achievements unlocked within the last hour
     */
    public function getRecentlyUnlocked(Request $request, $userId): JsonResponse
    {
        $authenticatedUserId = $request->attributes->get('user_id');
        if ($authenticatedUserId != $userId) {
            return response()->json(['message' => 'Unauthorized access to user data.'], 403);
        }

        $recentAchievements = UserAchievement::with('achievement')
            ->forUser($userId)
            ->completed()
            ->where('earned_at', '>=', now()->subHour())
            ->orderBy('earned_at', 'desc')
            ->get()
            ->map(function ($ua) {
                return [
                    'achievement_id' => $ua->achievement->achievement_id,
                    'achievement_name' => $ua->achievement->achievement_name,
                    'description' => $ua->achievement->description,
                    'badge_icon' => $ua->achievement->badge_icon,
                    'badge_color' => $ua->achievement->badge_color,
                    'rarity_level' => $ua->achievement->rarity_level,
                    'points_value' => $ua->achievement->points_value,
                    'earned_at' => $ua->earned_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $recentAchievements
        ]);
    }

    /**
     * Fetch user stats from tracking service
     */
    private function fetchUserStatsFromTracking($token, $userId): array
    {
        try {
            $client = new \GuzzleHttp\Client();
            // Use internal endpoint for service-to-service calls
            $response = $client->get("http://fitnease-tracking:80/api/internal/users/{$userId}/stats", [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 5,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $stats = $data['data'] ?? $data ?? [];

            // Map tracking service response to expected format
            return [
                'total_workouts' => $stats['completed_sessions'] ?? $stats['total_sessions'] ?? 0,
                'total_calories' => $stats['total_calories_burned'] ?? 0,
                'total_minutes' => $stats['total_exercise_time'] ?? 0,
                'current_streak' => $stats['current_streak'] ?? 0,
                'longest_streak' => $stats['longest_streak'] ?? 0,
                'group_workouts' => $stats['group_sessions_count'] ?? 0,
                'this_week_sessions' => $stats['this_week_sessions'] ?? 0,
            ];
        } catch (\Exception $e) {
            \Log::warning("Failed to fetch user stats from tracking: " . $e->getMessage());
            return [
                'total_workouts' => 0,
                'total_calories' => 0,
                'total_minutes' => 0,
                'current_streak' => 0,
                'longest_streak' => 0,
                'group_workouts' => 0,
            ];
        }
    }

    /**
     * Check if achievement criteria is met based on user stats
     */
    private function checkAchievementCriteria(Achievement $achievement, array $userStats): bool
    {
        $criteria = json_decode($achievement->criteria_json, true) ?? [];

        switch ($achievement->achievement_type) {
            case 'workout_count':
                $targetCount = $criteria['target_count'] ?? $criteria['workout_count'] ?? PHP_INT_MAX;
                return ($userStats['total_workouts'] ?? 0) >= $targetCount;

            case 'streak':
                $targetStreak = $criteria['target_streak'] ?? $criteria['streak_days'] ?? PHP_INT_MAX;
                return ($userStats['current_streak'] ?? 0) >= $targetStreak;

            case 'calories':
                $targetCalories = $criteria['target_calories'] ?? $criteria['calories_burned'] ?? PHP_INT_MAX;
                return ($userStats['total_calories'] ?? 0) >= $targetCalories;

            case 'time':
                $targetMinutes = $criteria['target_minutes'] ?? $criteria['total_minutes'] ?? PHP_INT_MAX;
                return ($userStats['total_minutes'] ?? 0) >= $targetMinutes;

            case 'social':
                $targetGroupWorkouts = $criteria['target_group_workouts'] ?? $criteria['group_workouts'] ?? PHP_INT_MAX;
                return ($userStats['group_workouts'] ?? 0) >= $targetGroupWorkouts;

            case 'special':
                // Special achievements are typically unlocked via specific triggers
                // For now, check if there's a simple criteria
                if (isset($criteria['first_workout']) && $criteria['first_workout']) {
                    return ($userStats['total_workouts'] ?? 0) >= 1;
                }
                return false;

            default:
                return false;
        }
    }
}

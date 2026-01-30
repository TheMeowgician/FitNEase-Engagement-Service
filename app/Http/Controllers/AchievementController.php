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
     * Get achievements that user hasn't seen yet (for showing modal on app open)
     * Also auto-unlocks the beginner "Welcome Newcomer" achievement if user doesn't have it
     */
    public function getUnseenAchievements(Request $request, $userId): JsonResponse
    {
        $authenticatedUserId = $request->attributes->get('user_id');
        if ($authenticatedUserId != $userId) {
            return response()->json(['message' => 'Unauthorized access to user data.'], 403);
        }

        // Auto-unlock beginner achievement if user doesn't have it yet
        $this->autoUnlockBeginnerAchievement($userId);

        $unseenAchievements = UserAchievement::with('achievement')
            ->forUser($userId)
            ->unseen()
            ->orderBy('earned_at', 'asc')
            ->get()
            ->map(function ($ua) {
                return [
                    'user_achievement_id' => $ua->user_achievement_id,
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
            'data' => $unseenAchievements
        ]);
    }

    /**
     * Auto-unlock the beginner "Welcome Newcomer" achievement if user doesn't have it
     */
    private function autoUnlockBeginnerAchievement($userId): void
    {
        try {
            // Find the beginner achievement (uses 'special' type with level_progression criteria)
            $beginnerAchievement = Achievement::where('achievement_type', 'special')
                ->whereRaw("JSON_EXTRACT(criteria_json, '$.level') = ?", ['beginner'])
                ->whereRaw("JSON_EXTRACT(criteria_json, '$.type') = ?", ['level_progression'])
                ->first();

            if (!$beginnerAchievement) {
                return;
            }

            // Check if user already has it
            $existing = UserAchievement::where('user_id', $userId)
                ->where('achievement_id', $beginnerAchievement->achievement_id)
                ->exists();

            if ($existing) {
                return;
            }

            // Unlock the beginner achievement
            UserAchievement::create([
                'user_id' => $userId,
                'achievement_id' => $beginnerAchievement->achievement_id,
                'progress_percentage' => 100.00,
                'is_completed' => true,
                'earned_at' => now(),
                'points_earned' => $beginnerAchievement->points_value,
            ]);

            \Log::info("Auto-unlocked beginner achievement for user {$userId}");
        } catch (\Exception $e) {
            \Log::warning("Failed to auto-unlock beginner achievement: " . $e->getMessage());
        }
    }

    /**
     * Mark achievements as seen by user (after they dismiss the modal)
     */
    public function markAchievementsSeen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'user_achievement_ids' => 'required|array|min:1',
            'user_achievement_ids.*' => 'integer'
        ]);

        $authenticatedUserId = $request->attributes->get('user_id');
        if ($authenticatedUserId != $validated['user_id']) {
            return response()->json(['message' => 'Unauthorized access to user data.'], 403);
        }

        $updated = UserAchievement::whereIn('user_achievement_id', $validated['user_achievement_ids'])
            ->where('user_id', $validated['user_id'])
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$updated} achievements as seen"
        ]);
    }

    /**
     * Unlock level-based achievement (Welcome, Intermediate, Advanced)
     * Called when user's fitness level changes
     */
    public function unlockLevelAchievement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'level' => 'required|string|in:beginner,intermediate,advanced'
        ]);

        $authenticatedUserId = $request->attributes->get('user_id');
        if ($authenticatedUserId != $validated['user_id']) {
            return response()->json(['message' => 'Unauthorized access to user data.'], 403);
        }

        // Find the level achievement (uses 'special' type with level_progression criteria)
        $achievement = Achievement::where('achievement_type', 'special')
            ->whereRaw("JSON_EXTRACT(criteria_json, '$.level') = ?", [$validated['level']])
            ->whereRaw("JSON_EXTRACT(criteria_json, '$.type') = ?", ['level_progression'])
            ->first();

        if (!$achievement) {
            return response()->json([
                'success' => false,
                'message' => 'Level achievement not found'
            ], 404);
        }

        // Check if already unlocked
        $existing = UserAchievement::where('user_id', $validated['user_id'])
            ->where('achievement_id', $achievement->achievement_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Achievement already unlocked',
                'data' => null
            ]);
        }

        // Unlock the achievement
        $userAchievement = UserAchievement::create([
            'user_id' => $validated['user_id'],
            'achievement_id' => $achievement->achievement_id,
            'progress_percentage' => 100.00,
            'is_completed' => true,
            'earned_at' => now(),
            'points_earned' => $achievement->points_value,
        ]);

        // Send notification
        $token = $request->bearerToken();
        try {
            $this->commsService->sendAchievementNotification($token, $validated['user_id'], $achievement->achievement_id);
        } catch (\Exception $e) {
            \Log::warning("Failed to send level achievement notification: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Level achievement unlocked',
            'data' => [
                'user_achievement_id' => $userAchievement->user_achievement_id,
                'achievement_id' => $achievement->achievement_id,
                'achievement_name' => $achievement->achievement_name,
                'description' => $achievement->description,
                'badge_icon' => $achievement->badge_icon,
                'badge_color' => $achievement->badge_color,
                'rarity_level' => $achievement->rarity_level,
                'points_value' => $achievement->points_value,
                'earned_at' => $userAchievement->earned_at,
            ]
        ]);
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

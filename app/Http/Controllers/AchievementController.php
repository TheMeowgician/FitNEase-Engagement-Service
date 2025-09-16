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
}

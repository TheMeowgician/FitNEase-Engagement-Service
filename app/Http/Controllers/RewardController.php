<?php

namespace App\Http\Controllers;

use App\Models\Reward;
use App\Models\UserAchievement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    public function getAvailableRewards($userId): JsonResponse
    {
        $userPoints = $this->getUserTotalPoints($userId);

        $rewards = Reward::available()
            ->orderBy('requirement_points', 'asc')
            ->get()
            ->map(function ($reward) use ($userPoints) {
                $reward->affordable = $userPoints >= $reward->requirement_points;
                return $reward;
            });

        return response()->json([
            'success' => true,
            'user_points' => $userPoints,
            'data' => $rewards
        ]);
    }

    public function redeemReward(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'reward_id' => 'required|integer|exists:rewards,reward_id'
        ]);

        $reward = Reward::findOrFail($validated['reward_id']);
        $userPoints = $this->getUserTotalPoints($validated['user_id']);

        if (!$reward->is_available) {
            return response()->json([
                'success' => false,
                'message' => 'Reward is not available'
            ], 400);
        }

        if ($userPoints < $reward->requirement_points) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient points to redeem this reward',
                'required_points' => $reward->requirement_points,
                'user_points' => $userPoints
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reward redeemed successfully',
            'data' => [
                'reward' => $reward,
                'points_deducted' => $reward->requirement_points,
                'remaining_points' => $userPoints - $reward->requirement_points
            ]
        ]);
    }

    public function getUserRewards($userId): JsonResponse
    {
        $userPoints = $this->getUserTotalPoints($userId);

        $earnedRewards = Reward::available()
            ->where('requirement_points', '<=', $userPoints)
            ->get();

        return response()->json([
            'success' => true,
            'user_points' => $userPoints,
            'data' => $earnedRewards
        ]);
    }

    private function getUserTotalPoints($userId): int
    {
        return UserAchievement::forUser($userId)
            ->completed()
            ->sum('points_earned');
    }
}

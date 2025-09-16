<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CommsService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('COMMS_SERVICE_URL', 'http://localhost:8001');
    }

    /**
     * Send achievement notification via comms service
     */
    public function sendAchievementNotification($token, $userId, $achievementId)
    {
        try {
            $notificationData = [
                'user_id' => $userId,
                'type' => 'achievement_unlocked',
                'achievement_id' => $achievementId
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/comms/achievement-notification', $notificationData);

            if ($response->successful()) {
                Log::info('Achievement notification sent successfully', [
                    'user_id' => $userId,
                    'achievement_id' => $achievementId
                ]);
                return $response->json();
            }

            Log::error('Failed to send achievement notification', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Comms service communication error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send reward notification via comms service
     */
    public function sendRewardNotification($token, $userId, $rewardId, $rewardType)
    {
        try {
            $notificationData = [
                'user_id' => $userId,
                'type' => 'reward_earned',
                'reward_id' => $rewardId,
                'reward_type' => $rewardType
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/comms/reward-notification', $notificationData);

            if ($response->successful()) {
                Log::info('Reward notification sent successfully', [
                    'user_id' => $userId,
                    'reward_id' => $rewardId
                ]);
                return $response->json();
            }

            Log::error('Failed to send reward notification', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Comms service communication error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send engagement milestone notification
     */
    public function sendEngagementMilestoneNotification($token, $userId, $milestoneType, $data)
    {
        try {
            $notificationData = [
                'user_id' => $userId,
                'type' => 'engagement_milestone',
                'milestone_type' => $milestoneType,
                'data' => $data
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/comms/engagement-notification', $notificationData);

            if ($response->successful()) {
                Log::info('Engagement milestone notification sent successfully', [
                    'user_id' => $userId,
                    'milestone_type' => $milestoneType
                ]);
                return $response->json();
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Comms service communication error: ' . $e->getMessage());
            return null;
        }
    }
}
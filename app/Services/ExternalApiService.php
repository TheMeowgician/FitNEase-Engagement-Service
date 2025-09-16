<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ExternalApiService
{
    private $client;
    private $mlServiceUrl;
    private $commsServiceUrl;
    private $trackingServiceUrl;
    private $authServiceUrl;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);

        $this->mlServiceUrl = env('ML_SERVICE_URL');
        $this->commsServiceUrl = env('COMMS_SERVICE_URL');
        $this->trackingServiceUrl = env('TRACKING_SERVICE_URL');
        $this->authServiceUrl = env('AUTH_SERVICE_URL');
    }

    /**
     * Get user behavior patterns from ML service
     */
    public function getUserPatterns($userId): ?array
    {
        try {
            $response = $this->client->get($this->mlServiceUrl . '/api/v1/user-patterns/' . $userId);
            $behaviorPatterns = json_decode($response->getBody(), true);

            Log::info('Retrieved user patterns from ML service', [
                'user_id' => $userId,
                'patterns_count' => count($behaviorPatterns ?? [])
            ]);

            return $behaviorPatterns;
        } catch (RequestException $e) {
            Log::error('Failed to retrieve user patterns from ML service', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Trigger achievement notification via Communications service
     */
    public function triggerAchievementNotification($userId, $achievementId): bool
    {
        try {
            $notificationData = [
                'user_id' => $userId,
                'type' => 'achievement_unlocked',
                'achievement_id' => $achievementId
            ];

            $response = $this->client->post($this->commsServiceUrl . '/comms/achievement-notification', [
                'json' => $notificationData
            ]);

            Log::info('Achievement notification sent successfully', [
                'user_id' => $userId,
                'achievement_id' => $achievementId
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (RequestException $e) {
            Log::error('Failed to send achievement notification', [
                'user_id' => $userId,
                'achievement_id' => $achievementId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user progress data from Tracking service
     */
    public function getUserProgress($userId): ?array
    {
        try {
            $response = $this->client->get($this->trackingServiceUrl . '/tracking/progress/' . $userId);
            $progressData = json_decode($response->getBody(), true);

            Log::info('Retrieved user progress from tracking service', [
                'user_id' => $userId
            ]);

            return $progressData;
        } catch (RequestException $e) {
            Log::error('Failed to retrieve user progress from tracking service', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validate user authentication via Auth service
     */
    public function validateUser($token): ?array
    {
        try {
            $response = $this->client->get($this->authServiceUrl . '/auth/validate', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);

            $userData = json_decode($response->getBody(), true);

            Log::info('User authentication validated successfully');

            return $userData;
        } catch (RequestException $e) {
            Log::error('Failed to validate user authentication', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send reward notification via Communications service
     */
    public function triggerRewardNotification($userId, $rewardId, $rewardType): bool
    {
        try {
            $notificationData = [
                'user_id' => $userId,
                'type' => 'reward_earned',
                'reward_id' => $rewardId,
                'reward_type' => $rewardType
            ];

            $response = $this->client->post($this->commsServiceUrl . '/comms/reward-notification', [
                'json' => $notificationData
            ]);

            Log::info('Reward notification sent successfully', [
                'user_id' => $userId,
                'reward_id' => $rewardId
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (RequestException $e) {
            Log::error('Failed to send reward notification', [
                'user_id' => $userId,
                'reward_id' => $rewardId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send engagement milestone notification
     */
    public function triggerEngagementMilestoneNotification($userId, $milestoneType, $data): bool
    {
        try {
            $notificationData = [
                'user_id' => $userId,
                'type' => 'engagement_milestone',
                'milestone_type' => $milestoneType,
                'data' => $data
            ];

            $response = $this->client->post($this->commsServiceUrl . '/comms/engagement-notification', [
                'json' => $notificationData
            ]);

            Log::info('Engagement milestone notification sent successfully', [
                'user_id' => $userId,
                'milestone_type' => $milestoneType
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (RequestException $e) {
            Log::error('Failed to send engagement milestone notification', [
                'user_id' => $userId,
                'milestone_type' => $milestoneType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get personalized achievement recommendations from ML service
     */
    public function getPersonalizedAchievements($userId): ?array
    {
        try {
            $response = $this->client->get($this->mlServiceUrl . '/api/v1/personalized-achievements/' . $userId);
            $recommendations = json_decode($response->getBody(), true);

            Log::info('Retrieved personalized achievements from ML service', [
                'user_id' => $userId,
                'recommendations_count' => count($recommendations ?? [])
            ]);

            return $recommendations;
        } catch (RequestException $e) {
            Log::error('Failed to retrieve personalized achievements from ML service', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send user engagement data to ML service for analysis
     */
    public function sendEngagementDataForAnalysis($userId, $engagementData): bool
    {
        try {
            $payload = [
                'user_id' => $userId,
                'engagement_data' => $engagementData
            ];

            $response = $this->client->post($this->mlServiceUrl . '/api/v1/engagement-analysis', [
                'json' => $payload
            ]);

            Log::info('Engagement data sent to ML service for analysis', [
                'user_id' => $userId
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (RequestException $e) {
            Log::error('Failed to send engagement data to ML service', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
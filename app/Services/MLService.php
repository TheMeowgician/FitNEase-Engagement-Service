<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MLService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('ML_SERVICE_URL', 'http://localhost:9000');
    }

    /**
     * Get user behavior patterns from ML service
     */
    public function getUserPatterns($userId)
    {
        try {
            $response = Http::timeout(5)->withHeaders([
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/api/v1/user-patterns/' . $userId);

            if ($response->successful()) {
                $behaviorPatterns = $response->json();

                Log::info('Retrieved user patterns from ML service', [
                    'user_id' => $userId,
                    'patterns_count' => count($behaviorPatterns ?? [])
                ]);

                return $behaviorPatterns;
            }

            Log::warning('ML service unavailable or endpoint not found', [
                'user_id' => $userId,
                'status' => $response->status(),
                'url' => $this->baseUrl . '/api/v1/user-patterns/' . $userId
            ]);

            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::info('ML service not available - returning empty patterns', [
                'user_id' => $userId,
                'service_url' => $this->baseUrl
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('ML service communication error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get personalized achievement recommendations from ML service
     */
    public function getPersonalizedAchievements($userId)
    {
        try {
            $response = Http::timeout(5)->withHeaders([
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/api/v1/personalized-achievements/' . $userId);

            if ($response->successful()) {
                $recommendations = $response->json();

                Log::info('Retrieved personalized achievements from ML service', [
                    'user_id' => $userId,
                    'recommendations_count' => count($recommendations ?? [])
                ]);

                return $recommendations;
            }

            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::info('ML service not available - returning empty recommendations', [
                'user_id' => $userId,
                'service_url' => $this->baseUrl
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('ML service communication error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send user engagement data to ML service for analysis
     */
    public function sendEngagementDataForAnalysis($userId, $engagementData)
    {
        try {
            $payload = [
                'user_id' => $userId,
                'engagement_data' => $engagementData
            ];

            $response = Http::timeout(5)->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/v1/engagement-analysis', $payload);

            if ($response->successful()) {
                Log::info('Engagement data sent to ML service for analysis', [
                    'user_id' => $userId
                ]);
                return true;
            }

            return false;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::info('ML service not available - skipping engagement analysis', [
                'user_id' => $userId,
                'service_url' => $this->baseUrl
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('ML service communication error: ' . $e->getMessage());
            return false;
        }
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrackingService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('TRACKING_SERVICE_URL', 'http://localhost:8006');
    }

    /**
     * Get user progress data from Tracking service
     */
    public function getUserProgress($token, $userId)
    {
        try {
            $response = Http::timeout(5)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/api/tracking/progress/' . $userId);

            if ($response->successful()) {
                $progressData = $response->json();

                Log::info('Retrieved user progress from tracking service', [
                    'user_id' => $userId
                ]);

                return $progressData;
            }

            Log::warning('Tracking service unavailable or endpoint not found', [
                'user_id' => $userId,
                'status' => $response->status(),
                'url' => $this->baseUrl . '/api/tracking/progress/' . $userId
            ]);

            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::info('Tracking service not available - returning empty progress data', [
                'user_id' => $userId,
                'service_url' => $this->baseUrl
            ]);
            return ['progress' => 0, 'total_workouts' => 0, 'last_workout' => null];
        } catch (\Exception $e) {
            Log::error('Tracking service communication error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user workout statistics for achievement calculations
     */
    public function getUserWorkoutStats($token, $userId)
    {
        try {
            $response = Http::timeout(5)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/api/tracking/user-stats/' . $userId);

            if ($response->successful()) {
                return $response->json();
            }

            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::info('Tracking service not available - returning empty stats', [
                'user_id' => $userId,
                'service_url' => $this->baseUrl
            ]);
            return ['total_workouts' => 0, 'total_duration' => 0, 'avg_intensity' => 0];
        } catch (\Exception $e) {
            Log::error('Tracking service communication error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user streak information
     */
    public function getUserStreak($token, $userId)
    {
        try {
            $response = Http::timeout(5)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/api/tracking/streak/' . $userId);

            if ($response->successful()) {
                return $response->json();
            }

            return null;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::info('Tracking service not available - returning empty streak data', [
                'user_id' => $userId,
                'service_url' => $this->baseUrl
            ]);
            return ['current_streak' => 0, 'longest_streak' => 0, 'last_workout_date' => null];
        } catch (\Exception $e) {
            Log::error('Tracking service communication error: ' . $e->getMessage());
            return null;
        }
    }
}
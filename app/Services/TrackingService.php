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
            $response = Http::withHeaders([
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

            Log::error('Failed to retrieve user progress from tracking service', [
                'user_id' => $userId,
                'status' => $response->status()
            ]);

            return null;

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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/api/tracking/user-stats/' . $userId);

            if ($response->successful()) {
                return $response->json();
            }

            return null;

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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/api/tracking/streak/' . $userId);

            if ($response->successful()) {
                return $response->json();
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Tracking service communication error: ' . $e->getMessage());
            return null;
        }
    }
}
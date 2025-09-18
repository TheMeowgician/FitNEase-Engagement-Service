<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\CommsService;
use App\Services\TrackingService;
use App\Services\MLService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceTestController extends Controller
{
    protected AuthService $authService;
    protected CommsService $commsService;
    protected TrackingService $trackingService;
    protected MLService $mlService;

    public function __construct(
        AuthService $authService,
        CommsService $commsService,
        TrackingService $trackingService,
        MLService $mlService
    ) {
        $this->authService = $authService;
        $this->commsService = $commsService;
        $this->trackingService = $trackingService;
        $this->mlService = $mlService;
    }

    public function testAuthService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['user_id'] ?? 1;

            $tests = [
                'user_profile' => $this->authService->getUserProfile($userId, $token),
                'user_access_validation' => $this->authService->validateUserAccess($userId, $token)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Auth service test completed',
                'service' => 'auth',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auth service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testCommsService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['user_id'] ?? 1;

            $tests = [
                'send_achievement_notification' => $this->commsService->sendAchievementNotification($token, $userId, 1),
                'send_reward_notification' => $this->commsService->sendRewardNotification($token, $userId, 1, 'points'),
                'send_engagement_milestone_notification' => $this->commsService->sendEngagementMilestoneNotification($token, $userId, 'workout_streak', ['streak_days' => 7])
            ];

            return response()->json([
                'success' => true,
                'message' => 'Comms service test completed',
                'service' => 'comms',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Comms service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testTrackingService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['user_id'] ?? 1;

            $tests = [
                'user_progress' => $this->trackingService->getUserProgress($token, $userId),
                'user_workout_stats' => $this->trackingService->getUserWorkoutStats($token, $userId),
                'user_streak' => $this->trackingService->getUserStreak($token, $userId)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Tracking service test completed',
                'service' => 'tracking',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testMLService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['user_id'] ?? 1;

            // Test ML service methods (check if MLService has these methods)
            $tests = [
                'ml_service_available' => class_exists('App\Services\MLService'),
                'ml_service_instantiated' => $this->mlService ? 'success' : 'failed'
            ];

            // Add actual ML service tests if the service has specific methods
            if (method_exists($this->mlService, 'getUserRecommendations')) {
                $tests['user_recommendations'] = $this->mlService->getUserRecommendations($token, $userId);
            }

            return response()->json([
                'success' => true,
                'message' => 'ML service test completed',
                'service' => 'ml',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ML service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testAllServices(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $allTests = [
                'auth_service' => $this->testAuthService($request)->getData(),
                'comms_service' => $this->testCommsService($request)->getData(),
                'tracking_service' => $this->testTrackingService($request)->getData(),
                'ml_service' => $this->testMLService($request)->getData()
            ];

            $overallSuccess = true;
            foreach ($allTests as $test) {
                if (!$test->success) {
                    $overallSuccess = false;
                    break;
                }
            }

            return response()->json([
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 'All service tests completed successfully' : 'Some service tests failed',
                'results' => $allTests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service testing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
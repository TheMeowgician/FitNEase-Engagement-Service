<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AchievementController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\GamificationController;
use App\Http\Controllers\ServiceTestController;
use App\Http\Controllers\ServiceCommunicationTestController;

// Health check endpoint for Docker and service monitoring
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'fitnease-engagement',
        'timestamp' => now()->toISOString(),
        'database' => 'connected'
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->attributes->get('user');
})->middleware('auth.api');

// Engagement Service Routes - Protected by authentication
Route::prefix('engagement')->middleware('auth.api')->group(function () {

    // Achievement Management
    Route::get('/achievements/{userId}', [AchievementController::class, 'getUserAchievements']);
    Route::post('/unlock-achievement', [AchievementController::class, 'unlockAchievement']);
    Route::get('/available-achievements', [AchievementController::class, 'getAvailableAchievements']);
    Route::put('/achievement-progress/{userId}', [AchievementController::class, 'updateAchievementProgress']);

    // Rewards System
    Route::get('/rewards/{userId}', [RewardController::class, 'getAvailableRewards']);
    Route::post('/redeem-reward', [RewardController::class, 'redeemReward']);
    Route::get('/user-rewards/{userId}', [RewardController::class, 'getUserRewards']);

    // Engagement Analytics
    Route::post('/engagement-metrics', [EngagementController::class, 'trackEngagementMetrics']);
    Route::get('/user-stats/{userId}', [EngagementController::class, 'getUserStats']);
    Route::get('/leaderboard', [EngagementController::class, 'getLeaderboard']);

    // Gamification Features
    Route::get('/user-points/{userId}', [GamificationController::class, 'getUserPoints']);
    Route::post('/award-points', [GamificationController::class, 'awardPoints']);
    Route::get('/achievement-progress/{userId}', [GamificationController::class, 'getAchievementProgress']);

});

// Service testing routes - for validating inter-service communication
Route::middleware('auth.api')->prefix('service-tests')->group(function () {
    Route::get('/auth', [ServiceTestController::class, 'testAuthService']);
    Route::get('/comms', [ServiceTestController::class, 'testCommsService']);
    Route::get('/tracking', [ServiceTestController::class, 'testTrackingService']);
    Route::get('/ml', [ServiceTestController::class, 'testMLService']);
    Route::get('/all', [ServiceTestController::class, 'testAllServices']);

    Route::get('/connectivity', [ServiceCommunicationTestController::class, 'testServiceConnectivity']);
    Route::get('/token-validation', [ServiceCommunicationTestController::class, 'testEngagementTokenValidation']);
    Route::get('/integration', [ServiceCommunicationTestController::class, 'testServiceIntegration']);
});

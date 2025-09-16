<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // All required performance indexes from the specification are already implemented
        // in the main table creation migrations:
        //
        // ✅ idx_user_achievements_progress ON UserAchievements(user_id, is_completed, progress_percentage)
        // ✅ idx_achievements_type_rarity ON Achievements(achievement_type, rarity_level, is_active)
        // ✅ idx_engagement_metrics_user_date ON EngagementMetrics(user_id, metric_date)
        // ✅ idx_user_achievements_earned ON UserAchievements(achievement_id, earned_at)
        // ✅ idx_user_achievements_points ON UserAchievements(user_id, points_earned, earned_at)
        //
        // This migration is kept for future performance optimizations if needed.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No indexes to drop as they were created in main table migrations
    }
};

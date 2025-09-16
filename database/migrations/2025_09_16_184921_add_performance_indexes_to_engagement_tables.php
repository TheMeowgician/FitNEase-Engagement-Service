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
        // Note: Some basic indexes were already created during table creation
        // Adding any additional performance indexes here

        Schema::table('user_achievements', function (Blueprint $table) {
            // Additional index for earned_at desc for leaderboard queries (if not exists)
            $table->index('earned_at', 'idx_user_achievements_earned_at_desc');
        });

        Schema::table('achievements', function (Blueprint $table) {
            // Additional index for points_value for reward calculations
            $table->index('points_value', 'idx_achievements_points_value');
        });

        Schema::table('engagement_metrics', function (Blueprint $table) {
            // Additional indexes for analytics queries
            $table->index('engagement_score', 'idx_engagement_metrics_score');
            $table->index('motivation_level', 'idx_engagement_metrics_motivation');
        });

        Schema::table('rewards', function (Blueprint $table) {
            // Index for requirement_points for affordability queries
            $table->index('requirement_points', 'idx_rewards_requirement_points');
            $table->index(['reward_type', 'is_available'], 'idx_rewards_type_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_achievements', function (Blueprint $table) {
            $table->dropIndex('idx_user_achievements_earned_at_desc');
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->dropIndex('idx_achievements_points_value');
        });

        Schema::table('engagement_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_engagement_metrics_score');
            $table->dropIndex('idx_engagement_metrics_motivation');
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->dropIndex('idx_rewards_requirement_points');
            $table->dropIndex('idx_rewards_type_available');
        });
    }
};

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
        Schema::create('engagement_metrics', function (Blueprint $table) {
            $table->id('metric_id');
            $table->unsignedBigInteger('user_id');
            $table->date('metric_date');
            $table->integer('session_count')->default(0);
            $table->integer('total_session_duration_minutes')->default(0);
            $table->integer('achievements_earned')->default(0);
            $table->integer('points_earned')->default(0);
            $table->integer('notification_interactions')->default(0);
            $table->json('feature_usage_json')->nullable();
            $table->decimal('engagement_score', 5, 2)->default(0.00);
            $table->enum('motivation_level', ['very_low', 'low', 'moderate', 'high', 'very_high'])->default('moderate');
            $table->timestamps();

            $table->unique(['user_id', 'metric_date']);
            $table->index(['user_id', 'metric_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engagement_metrics');
    }
};

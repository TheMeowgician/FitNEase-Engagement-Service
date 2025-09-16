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
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id('user_achievement_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('achievement_id');
            $table->decimal('progress_percentage', 5, 2)->default(0.00);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('earned_at')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->integer('points_earned')->default(0);
            $table->timestamps();

            $table->foreign('achievement_id')->references('achievement_id')->on('achievements')->onDelete('cascade');
            $table->unique(['user_id', 'achievement_id']);
            $table->index(['user_id', 'is_completed', 'progress_percentage']);
            $table->index(['achievement_id', 'earned_at']);
            $table->index(['user_id', 'points_earned', 'earned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};

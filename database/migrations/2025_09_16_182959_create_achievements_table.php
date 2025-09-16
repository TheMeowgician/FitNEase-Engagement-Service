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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id('achievement_id');
            $table->string('achievement_name', 100);
            $table->text('description')->nullable();
            $table->enum('achievement_type', ['workout_count', 'streak', 'calories', 'time', 'social', 'special']);
            $table->json('criteria_json');
            $table->integer('points_value')->default(0);
            $table->string('badge_icon', 255)->nullable();
            $table->string('badge_color', 7)->nullable();
            $table->enum('rarity_level', ['common', 'rare', 'epic', 'legendary'])->default('common');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['achievement_type', 'rarity_level', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};

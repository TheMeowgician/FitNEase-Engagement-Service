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
        Schema::create('rewards', function (Blueprint $table) {
            $table->id('reward_id');
            $table->string('reward_name', 100);
            $table->text('description')->nullable();
            $table->enum('reward_type', ['badge', 'points', 'feature_unlock', 'virtual_item']);
            $table->integer('requirement_points')->default(0);
            $table->string('reward_value', 255)->nullable();
            $table->string('reward_icon', 255)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};

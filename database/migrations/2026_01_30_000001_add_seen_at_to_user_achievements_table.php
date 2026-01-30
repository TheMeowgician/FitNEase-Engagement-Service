<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds seen_at column to track when user viewed the achievement notification
     */
    public function up(): void
    {
        Schema::table('user_achievements', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable()->after('notification_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_achievements', function (Blueprint $table) {
            $table->dropColumn('seen_at');
        });
    }
};

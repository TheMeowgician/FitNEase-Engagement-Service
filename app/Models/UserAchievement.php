<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAchievement extends Model
{
    protected $primaryKey = 'user_achievement_id';

    protected $fillable = [
        'user_id',
        'achievement_id',
        'progress_percentage',
        'is_completed',
        'earned_at',
        'notification_sent',
        'points_earned'
    ];

    protected $casts = [
        'progress_percentage' => 'decimal:2',
        'is_completed' => 'boolean',
        'notification_sent' => 'boolean',
        'points_earned' => 'integer',
        'earned_at' => 'datetime'
    ];

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class, 'achievement_id', 'achievement_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeInProgress($query)
    {
        return $query->where('is_completed', false)->where('progress_percentage', '>', 0);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeNotificationPending($query)
    {
        return $query->where('is_completed', true)->where('notification_sent', false);
    }
}

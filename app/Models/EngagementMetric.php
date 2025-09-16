<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngagementMetric extends Model
{
    protected $primaryKey = 'metric_id';

    protected $fillable = [
        'user_id',
        'metric_date',
        'session_count',
        'total_session_duration_minutes',
        'achievements_earned',
        'points_earned',
        'notification_interactions',
        'feature_usage_json',
        'engagement_score',
        'motivation_level'
    ];

    protected $casts = [
        'metric_date' => 'date',
        'session_count' => 'integer',
        'total_session_duration_minutes' => 'integer',
        'achievements_earned' => 'integer',
        'points_earned' => 'integer',
        'notification_interactions' => 'integer',
        'feature_usage_json' => 'array',
        'engagement_score' => 'decimal:2'
    ];

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('metric_date', $date);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate, $endDate]);
    }

    public function scopeHighEngagement($query)
    {
        return $query->where('engagement_score', '>=', 7.0);
    }

    public function scopeByMotivationLevel($query, $level)
    {
        return $query->where('motivation_level', $level);
    }
}

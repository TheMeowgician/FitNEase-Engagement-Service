<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    protected $primaryKey = 'reward_id';

    protected $fillable = [
        'reward_name',
        'description',
        'reward_type',
        'requirement_points',
        'reward_value',
        'reward_icon',
        'is_available'
    ];

    protected $casts = [
        'requirement_points' => 'integer',
        'is_available' => 'boolean'
    ];

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('reward_type', $type);
    }

    public function scopeAffordableFor($query, $userPoints)
    {
        return $query->where('requirement_points', '<=', $userPoints);
    }
}

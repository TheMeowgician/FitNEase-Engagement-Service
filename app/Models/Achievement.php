<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    protected $primaryKey = 'achievement_id';

    protected $fillable = [
        'achievement_name',
        'description',
        'achievement_type',
        'criteria_json',
        'points_value',
        'badge_icon',
        'badge_color',
        'rarity_level',
        'is_active'
    ];

    protected $casts = [
        'criteria_json' => 'array',
        'is_active' => 'boolean',
        'points_value' => 'integer'
    ];

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class, 'achievement_id', 'achievement_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('achievement_type', $type);
    }

    public function scopeByRarity($query, $rarity)
    {
        return $query->where('rarity_level', $rarity);
    }
}

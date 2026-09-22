<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'phone',
        'address',
        'timezone',
        'default_capacity',
        'max_group_size',
        'slot_duration_minutes',
        'cancellation_cutoff_hours',
        'waitlist_confirm_minutes',
        'no_show_strike_limit',
        'no_show_suspension_days',
        'session_horizon_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_capacity' => 'integer',
            'max_group_size' => 'integer',
            'slot_duration_minutes' => 'integer',
            'cancellation_cutoff_hours' => 'integer',
            'waitlist_confirm_minutes' => 'integer',
            'no_show_strike_limit' => 'integer',
            'no_show_suspension_days' => 'integer',
            'session_horizon_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function trainers(): HasMany
    {
        return $this->hasMany(Trainer::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class);
    }

    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    public function memberGroups(): HasMany
    {
        return $this->hasMany(MemberGroup::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

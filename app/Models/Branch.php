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
        'max_group_size',
        'hourly_rate',
        'max_trainees',
        'booking_window_days',
        'max_booking_hours',
        'payment_instructions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_group_size' => 'integer',
            'hourly_rate' => 'decimal:2',
            'max_trainees' => 'integer',
            'booking_window_days' => 'integer',
            'max_booking_hours' => 'integer',
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

    public function memberGroups(): HasMany
    {
        return $this->hasMany(MemberGroup::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

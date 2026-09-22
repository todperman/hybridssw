<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'credits',
        'price',
        'validity_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'price' => 'decimal:2',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function memberPackages(): HasMany
    {
        return $this->hasMany(MemberPackage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

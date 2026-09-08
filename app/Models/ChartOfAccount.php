<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return match ($this->account_type) {
            'ASSET' => 'text-bg-success',
            'LIABILITY' => 'text-bg-warning',
            'EQUITY' => 'text-bg-info',
            'REVENUE' => 'text-bg-primary',
            'EXPENSE' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function currentBudget(?int $year = null)
    {
        $year = $year ?: date('Y');

        return $this->budgets()->where('year', $year)->first();
    }

    public function costCenter(): HasOne
    {
        return $this->hasOne(CostCenter::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    public function expeditionMappings(): HasMany
    {
        return $this->hasMany(ExpeditionMapping::class);
    }

    public function defaultExpeditionMapping(): HasOne
    {
        return $this->hasOne(ExpeditionMapping::class)->where('is_active', true)->latestOfMany();
    }
}

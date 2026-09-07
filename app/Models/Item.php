<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'estimated_unit_price' => 'decimal:2',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'safety_stock' => 'integer',
        'reorder_point' => 'integer',
        'lead_time_days' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(StockLedger::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ItemConversion::class);
    }

    public function getTotalAvailableAttribute(): int
    {
        return $this->stockBalances->sum(fn ($sb) => $sb->available);
    }

    public function getTotalOnHandAttribute(): int
    {
        return $this->stockBalances->sum('on_hand');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'on_hand' => 'integer',
        'reserved' => 'integer',
        'allocated' => 'integer',
        'in_transit' => 'integer',
        'hold' => 'integer',
        'damaged' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // Available = On Hand - Reserved - Hold - Damaged
    public function getAvailableAttribute(): int
    {
        return max(0, $this->on_hand - $this->reserved - $this->hold - $this->damaged);
    }

    public function getEffectiveMinStockAttribute(): int
    {
        return $this->min_stock !== null ? $this->min_stock : (int) ($this->item?->min_stock ?? 10);
    }

    public function getEffectiveMaxStockAttribute(): int
    {
        return $this->max_stock !== null ? $this->max_stock : (int) ($this->item?->max_stock ?? 200);
    }

    public function getStockStatusAttribute(): string
    {
        $min = $this->effective_min_stock;
        $max = $this->effective_max_stock;
        $available = $this->available;

        if ($available <= $min) {
            return 'CRITICAL_LOW';
        } elseif ($available <= ($min * 1.5)) {
            return 'WARNING';
        } elseif ($max > 0 && $available > $max) {
            return 'OVERSTOCK';
        }

        return 'OPTIMAL';
    }
}

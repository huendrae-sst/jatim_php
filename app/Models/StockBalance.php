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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwitchingStock extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'qty_requested' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SwitchingStockItem::class);
    }

    public function getTotalQtyAttribute(): int
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return (int) $this->items->sum('qty_requested');
        }

        $sum = $this->items()->sum('qty_requested');

        return (int) ($sum ?: $this->qty_requested);
    }

    public function getTotalItemsCountAttribute(): int
    {
        if ($this->relationLoaded('items')) {
            return $this->items->count();
        }

        $count = $this->items()->count();

        return (int) ($count ?: ($this->item_id ? 1 : 0));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function sourceOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'source_organization_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'destination_organization_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}

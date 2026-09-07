<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'qty_requested' => 'integer',
        'qty_approved' => 'integer',
        'qty_allocated' => 'integer',
        'qty_picked' => 'integer',
        'qty_packed' => 'integer',
        'qty_shipped' => 'integer',
        'qty_received' => 'integer',
        'unit_price_ref' => 'decimal:2',
        'subtotal_ref' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderAllocation::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(Discrepancy::class);
    }
}

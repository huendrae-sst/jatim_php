<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discrepancy extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'qty_expected' => 'integer',
        'qty_actual' => 'integer',
        'qty_damaged' => 'integer',
    ];

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(Receiving::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
